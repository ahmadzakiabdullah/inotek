<?php

namespace App\Http\Controllers\Judge;

use App\Events\ScoreUpdated;
use App\Http\Controllers\Controller;
use App\Models\CompetitionSession;
use App\Models\JudgeAssignment;
use App\Models\Project;
use App\Models\Score;
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
        $activeSession = CompetitionSession::where('is_active', true)->first();

        if (! $activeSession) {
            return Inertia::render('judge/Dashboard', [
                'projects' => [],
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
        $activeSession = CompetitionSession::where('is_active', true)->first();

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

        if (! $assignment) {
            abort(403, 'This project is not assigned to you for evaluation.');
        }

        // Get mapped rubric
        $category = $project->category;
        $rubric = $category ? $category->rubrics()->with('items')->first() : null;

        if (! $rubric) {
            abort(404, 'Evaluation rubric not found for this project category.');
        }

        // Get existing score if any
        $existingScore = Score::where('project_id', $project->id)
            ->where('judge_id', $judgeId)
            ->where('session_id', $activeSession->id)
            ->where('round_no', $roundNo)
            ->first();

        return Inertia::render('judge/EvaluationForm', [
            'project' => $project->load(['category', 'teamMembers', 'user']),
            'rubric' => $rubric,
            'existingScore' => $existingScore,
            'roundNo' => $roundNo,
            'isLocked' => $roundNo === 2 && $activeSession->r2_locked,
        ]);
    }

    /**
     * Store or update evaluation scores.
     */
    public function store(Project $project, Request $request): RedirectResponse
    {
        $activeSession = CompetitionSession::where('is_active', true)->first();

        if (! $activeSession) {
            return back()->with('error', 'No active competition session.');
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

        if (! $assignment) {
            return back()->with('error', 'Evaluation not allowed for this project.');
        }

        // Check if Round 2 is locked
        if ($roundNo === 2 && $activeSession->r2_locked) {
            return back()->with('error', 'Round 2 evaluation has been locked and cannot be modified.');
        }

        $rubric = $project->category?->rubrics()->with('items')->first();
        if (! $rubric || $rubric->items->isEmpty()) {
            return back()->withErrors(['scores' => 'No evaluation rubric is configured for this project category.']);
        }

        $scoreRules = [
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
        foreach ($rubric->items as $item) {
            $scoreRules['scores.'.$item->id] = ['required'];
        }

        // Require a score for every rubric item, and reject IDs outside this rubric.
        $request->validate([
            'scores' => $scoreRules,
            'comments' => 'nullable|string',
            'best_presenter' => 'nullable|string|max:100',
        ]);

        $scoreDetails = $request->input('scores');
        $total = $this->calculator->calculateTotal($project, $scoreDetails);

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
                    'success'
                ));
            }
        } catch (\Exception $e) {
            // Keep resilient
        }

        return redirect()->route('judge.evaluations.index')->with('success', 'Evaluation has been saved successfully.');
    }
}
