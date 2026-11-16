<?php

namespace App\Http\Controllers\Judge;

use App\Events\ScoreUpdated;
use App\Http\Controllers\Controller;
use App\Models\CompetitionSession;
use App\Models\JudgeAssignment;
use App\Models\Project;
use App\Models\Score;
use App\Models\Rubric;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\AuditLogger;
use App\Services\ScoreCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JudgeController extends Controller
{
    protected ScoreCalculator $calculator;

    public function __construct(ScoreCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    /**
     * Display a listing of assigned projects for evaluation.
     */
    public function index(): Response
    {
        $activeSession = CompetitionSession::active();

        if (! $activeSession) {
            return Inertia::render('judge/Dashboard', [
                'assignments' => [],
                'activeSession' => null,
                'error' => 'No active competition session found at this time.',
            ]);
        }

        $judgeId = auth()->id();

        // Get assignments for this judge
        $assignments = JudgeAssignment::with([
            'project' => function ($query) {
                $query->with(['category', 'teamMembers']);
            },
        ])
            ->where('judge_id', $judgeId)
            ->where('session_id', $activeSession->id)
            // Skip assignments whose project has since been deleted.
            ->whereHas('project')
            ->get();

        // Get existing scores submitted by this judge
        $scores = Score::where('judge_id', $judgeId)
            ->where('session_id', $activeSession->id)
            ->get()
            ->keyBy(function ($score) {
                return $score->project_id.'-'.$score->round_no;
            });

        // Format assignments list for the UI
        $mappedProjects = $assignments->map(function ($assignment) use ($scores) {
            $project = $assignment->project;
            $roundNo = $assignment->round_no;
            $scoreKey = $project->id.'-'.$roundNo;
            $existingScore = $scores->get($scoreKey);

            return [
                'id' => $project->id,
                'title' => $project->title,
                'pcode' => $project->pcode,
                'category_name' => $project->category->name ?? 'N/A',
                'team_count' => $project->teamMembers->count(),
                'round_no' => $roundNo,
                'is_evaluated' => $existingScore !== null,
                'total_score' => $existingScore ? $existingScore->total : null,
                'assignment_id' => $assignment->id,
            ];
        });

        return Inertia::render('judge/Dashboard', [
            'assignments' => $mappedProjects,
            'activeSession' => $activeSession,
        ]);
    }

    /**
     * Show the dynamic evaluation scorecard.
     */
    public function show(Project $project, Request $request): Response
    {
        $activeSession = CompetitionSession::active();

        if (! $activeSession) {
            abort(404, 'No active competition session.');
        }

        $judgeId = auth()->id();
        $validated = $request->validate([
            'round' => ['sometimes', 'integer', 'in:1,2'],
        ]);
        $roundNo = (int) ($validated['round'] ?? 1);

        // Verify assignment exists
        $assignment = JudgeAssignment::where('project_id', $project->id)
            ->where('judge_id', $judgeId)
            ->where('session_id', $activeSession->id)
            ->where('round_no', $roundNo)
            ->first();

        if (! $assignment || $project->status !== Project::STATUS_APPROVED) {
            abort(403, 'This project is not assigned to you for evaluation.');
        }

        if ($roundNo === 2 && ! $this->calculator->isJudgeEligibleForRound2($project->id, $request->user()->id, $activeSession->id)) {
            abort(403, 'You judged this project in Round 1, so you cannot evaluate it in Round 2.');
        }

        $existingScore = Score::where('project_id', $project->id)
            ->where('judge_id', $judgeId)
            ->where('session_id', $activeSession->id)
            ->where('round_no', $roundNo)
            ->first();

        // Existing evaluations keep their rubric version. New evaluations use the latest.
        $rubric = $existingScore ? $this->rubricForScore($existingScore) : null;
        $rubric ??= $project->category?->rubricsForSession($project->session_id)->with('items')->orderByDesc('rubrics.id')->first();

        if (! $rubric) {
            abort(404, 'Evaluation rubric not found for this project category.');
        }

        $project->load(['category', 'teamMembers', 'user']);

        return Inertia::render('judge/EvaluationForm', [
            // Only what the scorecard shows: no contact details, admin comments,
            // certificate hash or unpublished award level.
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'pcode' => $project->pcode,
                'abstract' => $project->abstract,
                'poster_url' => $project->poster_url,
                'video_url' => $project->video_url,
                'institution_type' => $project->institution_type,
                'category' => $project->category ? [
                    'id' => $project->category->id,
                    'name' => $project->category->name,
                ] : null,
                'team_members' => $project->teamMembers->map(fn ($member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                ])->values()->all(),
                'user' => $project->user ? [
                    'id' => $project->user->id,
                    'name' => $project->user->name,
                ] : null,
            ],
            'rubric' => $rubric,
            'existingScore' => $existingScore,
            'roundNo' => $roundNo,
            'isLocked' => $this->isRoundLocked($roundNo, $activeSession),
        ]);
    }

    /**
     * Store or update evaluation scores.
     */
    public function store(Project $project, Request $request): RedirectResponse
    {
        $activeSession = CompetitionSession::active();

        if (! $activeSession) {
            return $this->backWithError('No active competition session.');
        }

        $judgeId = auth()->id();
        $validated = $request->validate([
            'round_no' => ['required', 'integer', 'in:1,2'],
        ]);
        $roundNo = (int) $validated['round_no'];

        // Verify assignment exists
        $assignment = JudgeAssignment::where('project_id', $project->id)
            ->where('judge_id', $judgeId)
            ->where('session_id', $activeSession->id)
            ->where('round_no', $roundNo)
            ->first();

        if (! $assignment || $project->status !== Project::STATUS_APPROVED) {
            return $this->backWithError('Evaluation not allowed for this project.');
        }

        // Re-check at scoring time: the check at assignment time can be outdated
        // if the project's supervisor email changed since.
        if ($this->calculator->hasConflictOfInterest($project, $request->user())) {
            return $this->backWithError('You have a conflict of interest with this project (owner or supervisor) and cannot evaluate it.');
        }

        // Defence in depth: assignment already enforces this, but a Round 2
        // score must never come from a Round 1 judge of the same project.
        if ($roundNo === 2 && ! $this->calculator->isJudgeEligibleForRound2($project->id, $request->user()->id, $activeSession->id)) {
            return $this->backWithError('You judged this project in Round 1, so you cannot evaluate it in Round 2.');
        }

        if ($this->isRoundLocked($roundNo, $activeSession)) {
            return $this->backWithError($roundNo === 1
                ? 'Round 1 evaluation is closed because Round 2 judging has started.'
                : 'Round 2 evaluation has been locked and cannot be modified.');
        }

        $existingScore = Score::where('project_id', $project->id)
            ->where('judge_id', $judgeId)
            ->where('session_id', $activeSession->id)
            ->where('round_no', $roundNo)
            ->first();
        $rubric = $existingScore ? $this->rubricForScore($existingScore) : null;
        $rubric ??= $project->category?->rubricsForSession($project->session_id)->with('items')->orderByDesc('rubrics.id')->first();

        if (! $rubric || $rubric->items->isEmpty()) {
            return back()->withErrors(['scores' => 'No evaluation rubric is configured for this project category.']);
        }

        $scoreRules = [
            'bail',
            'required',
            'array',
            'size:'.$rubric->items->count(),
            function (string $attribute, mixed $value, \Closure $fail) use ($rubric): void {
                foreach ($rubric->items as $item) {
                    $score = $value[$item->id] ?? $value[(string) $item->id] ?? null;

                    if (! is_numeric($score) || (float) $score < 0 || (float) $score > $item->max_points) {
                        $fail('Each rubric criterion must have a numeric score within its allowed range.');

                        return;
                    }
                }

                $allowedIds = $rubric->items->map(fn ($item) => (string) $item->id)->all();
                $submittedIds = array_map('strval', array_keys($value));
                if (array_diff($submittedIds, $allowedIds) !== []) {
                    $fail('The scores contain criteria that are not part of this rubric.');
                }
            },
        ];
        // Require a score for every rubric item, and reject IDs outside this rubric.
        $rules = [
            'scores' => $scoreRules,
            'comments' => ['nullable', 'string', 'max:5000'],
            'best_presenter' => ['nullable', 'string', 'max:100'],
        ];
        foreach ($rubric->items as $item) {
            $rules['scores.'.$item->id] = ['required'];
        }
        $request->validate($rules);

        $scoreDetails = $request->input('scores');
        $total = $this->calculator->calculateTotal($project, $scoreDetails, $rubric);

        $score = Score::updateOrCreate(
            [
                'project_id' => $project->id,
                'judge_id' => $judgeId,
                'session_id' => $activeSession->id,
                'round_no' => $roundNo,
            ],
            [
                'total' => $total,
                'score_details' => $scoreDetails,
                'comments' => $request->input('comments'),
                'best_presenter' => $request->input('best_presenter'),
            ]
        );

        AuditLogger::log(
            'SUBMIT_SCORE',
            "Judge '{$judgeId}' saved an evaluation for project '{$project->title}' in Round {$roundNo}."
        );

        event(new ScoreUpdated($project));

        // Notify Admins
        try {
            $judgeName = auth()->user()->name;
            $admins = User::withRole('admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new SystemNotification(
                    'Project Evaluation Submitted',
                    "Judge '{$judgeName}' has submitted evaluation for project '{$project->title}' ({$project->pcode}) in Round {$roundNo}.",
                    '/dashboard/assignments',
                    'success',
                    mailEvent: 'score_submitted',
                ));
            }
        } catch (\Exception $e) {
            // Keep resilient
        }

        return $this->backWithSuccess('Evaluation has been saved successfully.', redirect()->route('judge.evaluations.index'));
    }

    /**
     * Round 1 freezes once Round 2 judging starts; Round 2 freezes when results are finalized.
     */
    private function isRoundLocked(int $roundNo, CompetitionSession $session): bool
    {
        return $roundNo === 1
            ? $this->calculator->isRoundOneLocked($session->id)
            : (bool) $session->r2_locked;
    }

    private function rubricForScore(Score $score): ?Rubric
    {
        $itemIds = array_map('strval', array_keys($score->score_details ?? []));
        if ($itemIds === []) {
            return null;
        }

        return Rubric::with('items')
            ->whereHas('items', fn ($query) => $query->whereIn('id', $itemIds))
            ->get()
            ->first(fn (Rubric $rubric) => array_diff(
                $itemIds,
                $rubric->items->pluck('id')->map(fn ($id) => (string) $id)->all()
            ) === []);
    }
}
