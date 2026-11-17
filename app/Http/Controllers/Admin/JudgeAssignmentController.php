<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompetitionSession;
use App\Models\JudgeAssignment;
use App\Models\Project;
use App\Models\Score;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\AuditLogger;
use App\Services\JudgeAutoAssigner;
use App\Services\ScoreCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class JudgeAssignmentController extends Controller
{
    /** Maximum project/judge pairs accepted in one bulk request. */
    public const MAX_BULK_PAIRS = 1000;

    protected ScoreCalculator $calculator;

    public function __construct(ScoreCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    /**
     * The assignment workspace: every approved project with its judges, plus
     * what the page needs to grey out ineligible judges before submitting.
     * The server re-checks every rule when an assignment is made.
     */
    public function index(): Response
    {
        $activeSession = CompetitionSession::active();
        $autoAssign = [
            'enabled' => JudgeAutoAssigner::isEnabled(),
            'judgesPerProject' => JudgeAutoAssigner::judgesPerProject(),
            'max' => JudgeAutoAssigner::MAX_JUDGES_PER_PROJECT,
        ];

        if (! $activeSession) {
            return Inertia::render('admin/assignments/Index', [
                'projects' => [],
                'judges' => [],
                'assignments' => [],
                'activeSession' => null,
                'roundOneComplete' => false,
                'roundOneLocked' => false,
                'targetJudges' => JudgeAutoAssigner::judgesPerProject(),
                'autoAssign' => $autoAssign,
            ]);
        }

        $judges = User::withRole('judge')->orderBy('name')->get(['id', 'name', 'email']);
        $judgeIds = $judges->pluck('id')->flip();
        $judgeIdByEmail = $judges->mapWithKeys(fn (User $judge) => [strtolower(trim($judge->email)) => $judge->id]);

        $projects = Project::where('session_id', $activeSession->id)
            ->where('status', Project::STATUS_APPROVED)
            ->with('category:id,name,code')
            ->get(['id', 'pcode', 'title', 'category_id', 'user_id', 'supervisor_email']);

        $assignments = JudgeAssignment::where('session_id', $activeSession->id)
            ->whereHas('project')
            ->whereHas('judge')
            ->get(['id', 'project_id', 'judge_id', 'round_no']);

        $scoredKeys = Score::where('session_id', $activeSession->id)
            ->get(['project_id', 'judge_id', 'round_no'])
            ->mapWithKeys(fn (Score $score) => ["{$score->project_id}-{$score->judge_id}-{$score->round_no}" => true]);

        $roundOneJudges = $this->roundOneJudgesByProject($activeSession);
        $shortlistedIds = array_flip($this->shortlistedProjectIds($activeSession));

        return Inertia::render('admin/assignments/Index', [
            'projects' => $projects
                ->sortBy(fn (Project $project) => [$project->category->code ?? '', $project->pcode ?? '', $project->title])
                ->map(function (Project $project) use ($judgeIds, $judgeIdByEmail, $roundOneJudges, $shortlistedIds) {
                    // Conflicts of interest, keyed by judge ID (same rule as ScoreCalculator::hasConflictOfInterest).
                    $conflicts = [];
                    $supervisorJudgeId = $project->supervisor_email !== null
                        ? $judgeIdByEmail->get(strtolower(trim($project->supervisor_email)))
                        : null;
                    if ($supervisorJudgeId !== null) {
                        $conflicts[$supervisorJudgeId] = 'supervisor';
                    }
                    if ($judgeIds->has($project->user_id)) {
                        $conflicts[$project->user_id] = 'owner';
                    }

                    return [
                        'id' => $project->id,
                        'pcode' => $project->pcode,
                        'title' => $project->title,
                        'category' => $project->category ? ['id' => $project->category->id, 'name' => $project->category->name] : null,
                        'shortlisted' => isset($shortlistedIds[$project->id]),
                        'conflicts' => (object) $conflicts,
                        'round_one_judge_ids' => $roundOneJudges[$project->id] ?? [],
                    ];
                })
                ->values(),
            'judges' => $judges->map(fn (User $judge) => ['id' => $judge->id, 'name' => $judge->name, 'email' => $judge->email])->values(),
            'assignments' => $assignments->map(fn (JudgeAssignment $assignment) => [
                'id' => $assignment->id,
                'project_id' => $assignment->project_id,
                'judge_id' => $assignment->judge_id,
                'round_no' => $assignment->round_no,
                'scored' => $scoredKeys->has("{$assignment->project_id}-{$assignment->judge_id}-{$assignment->round_no}"),
            ])->values(),
            'activeSession' => $activeSession->only(['id', 'name', 'is_active', 'r2_locked']),
            'roundOneComplete' => $this->calculator->isRoundOneComplete($activeSession->id),
            'roundOneLocked' => $this->calculator->isRoundOneLocked($activeSession->id),
            'targetJudges' => JudgeAutoAssigner::judgesPerProject(),
            'autoAssign' => $autoAssign,
        ]);
    }

    /**
     * Assign one judge to one project.
     */
    public function store(Request $request): RedirectResponse
    {
        $activeSession = CompetitionSession::active();

        if (! $activeSession) {
            return $this->backWithError('No active competition session.');
        }

        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'judge_id' => 'required|exists:users,id',
            'round_no' => 'required|integer|in:1,2',
        ]);
        $roundNo = (int) $validated['round_no'];

        $context = $this->pairContext($activeSession, $roundNo);
        [$project, $judge, $error] = $this->checkPair($activeSession, $roundNo, (int) $validated['project_id'], (int) $validated['judge_id'], $context);

        // The specific reason for this pair is more useful than the round-wide gate.
        $error ??= $this->roundGateError($activeSession, $roundNo);

        if ($error !== null || $project === null || $judge === null) {
            return $this->backWithError($error ?? 'The judge could not be assigned.');
        }

        $this->createAssignments($activeSession, $roundNo, [[$project, $judge]]);

        return $this->backWithSuccess('Judge has been assigned successfully.');
    }

    /**
     * Assign several project/judge pairs at once. Each pair is checked with the
     * same rules as a single assignment; ineligible pairs are skipped and listed.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $activeSession = CompetitionSession::active();

        if (! $activeSession) {
            return $this->backWithError('No active competition session.');
        }

        $validated = $request->validate([
            'round_no' => ['required', 'integer', 'in:1,2'],
            'pairs' => ['required', 'array', 'min:1', 'max:'.self::MAX_BULK_PAIRS],
            'pairs.*.project_id' => ['required', 'integer'],
            'pairs.*.judge_id' => ['required', 'integer'],
        ]);
        $roundNo = (int) $validated['round_no'];

        if ($gateError = $this->roundGateError($activeSession, $roundNo)) {
            return $this->backWithError($gateError);
        }

        $context = $this->pairContext($activeSession, $roundNo);
        $toCreate = [];
        $skipped = [];

        foreach ($validated['pairs'] as $pair) {
            [$project, $judge, $error] = $this->checkPair($activeSession, $roundNo, (int) $pair['project_id'], (int) $pair['judge_id'], $context);

            if ($error !== null || $project === null || $judge === null) {
                $label = ($project ? ($project->pcode ?? $project->title) : "project #{$pair['project_id']}")
                    .' – '.($judge->name ?? "judge #{$pair['judge_id']}");
                $skipped[] = "{$label}: {$error}";

                continue;
            }

            $toCreate[] = [$project, $judge];
            // Treat it as assigned for the rest of this request (no duplicates within one batch).
            $context['assigned']["{$project->id}-{$judge->id}"] = true;
        }

        $created = $this->createAssignments($activeSession, $roundNo, $toCreate);

        if ($skipped !== []) {
            $shown = implode('; ', array_slice($skipped, 0, 5));
            $more = count($skipped) > 5 ? ' (and '.(count($skipped) - 5).' more)' : '';

            return $this->backWithError("Assigned {$created}, skipped ".count($skipped)." — {$shown}{$more}.");
        }

        return $this->backWithSuccess($created === 1
            ? 'Judge has been assigned successfully.'
            : "Assigned {$created} judge assignments.");
    }

    /**
     * Assign judges in bulk so each project in the round has the requested
     * number of judges, with the workload spread evenly.
     */
    public function autoAssign(Request $request, JudgeAutoAssigner $assigner): RedirectResponse
    {
        if (! JudgeAutoAssigner::isEnabled()) {
            return $this->backWithError('Automatic judge assignment is turned off in System Settings.');
        }

        $activeSession = CompetitionSession::active();

        if (! $activeSession) {
            return $this->backWithError('No active competition session.');
        }

        $validated = $request->validate([
            'round_no' => ['required', 'integer', 'in:1,2'],
            'judges_per_project' => ['required', 'integer', 'min:1', 'max:'.JudgeAutoAssigner::MAX_JUDGES_PER_PROJECT],
        ]);
        $roundNo = (int) $validated['round_no'];

        if ($gateError = $this->roundGateError($activeSession, $roundNo)) {
            return $this->backWithError($gateError);
        }

        $result = $assigner->assign($activeSession, $roundNo, (int) $validated['judges_per_project']);
        $createdCount = count($result['created']);

        AuditLogger::log(
            'AUTO_ASSIGN_JUDGES',
            "Auto-assigned {$createdCount} judge assignment(s) for Round {$roundNo} ({$validated['judges_per_project']} judges per project, {$result['projects_considered']} project(s))."
        );

        $this->notifyJudges(
            collect($result['created'])->map(fn (array $item) => $item['judge']),
            $roundNo,
        );

        if ($result['projects_considered'] === 0) {
            return $this->backWithError($roundNo === 2
                ? 'There are no shortlisted projects to assign for Round 2.'
                : 'There are no approved projects to assign in this session.');
        }

        if ($result['unfilled'] !== []) {
            $codes = collect($result['unfilled'])
                ->map(fn (array $item) => ($item['project']->pcode ?? $item['project']->title)." (short {$item['missing']})")
                ->take(10)
                ->implode(', ');

            return $this->backWithError(
                "Created {$createdCount} assignment(s), but not enough eligible judges for ".count($result['unfilled'])." project(s): {$codes}. Add judges or lower the number of judges per project."
            );
        }

        return $this->backWithSuccess($createdCount === 0
            ? 'Every project already has enough judges. No new assignments were needed.'
            : "Created {$createdCount} judge assignment(s) for Round {$roundNo}.");
    }

    /**
     * Remove a judge assignment.
     */
    public function destroy(JudgeAssignment $assignment): RedirectResponse
    {
        $activeSession = CompetitionSession::active();

        if (! $activeSession || $assignment->session_id !== $activeSession->id) {
            return $this->backWithError('Operation not allowed.');
        }

        // If Round 2 assignment and locked, block deletion
        if ($assignment->round_no === 2 && $activeSession->r2_locked) {
            return $this->backWithError('Round 2 assignment is locked and cannot be deleted.');
        }

        // A submitted score would stay in the averages (and keep a Round 2
        // finalist ranked, with its award) after its assignment is gone.
        $hasScore = Score::where('project_id', $assignment->project_id)
            ->where('judge_id', $assignment->judge_id)
            ->where('session_id', $assignment->session_id)
            ->where('round_no', $assignment->round_no)
            ->exists();

        if ($hasScore) {
            return $this->backWithError('This judge has already submitted a score for this assignment, so it cannot be removed.');
        }

        $judgeName = $assignment->judge->name ?? 'Unknown Judge';
        $projectTitle = $assignment->project->title ?? 'Unknown Project';
        $roundNo = $assignment->round_no;

        $assignment->delete();

        AuditLogger::log(
            'REMOVE_JUDGE_ASSIGNMENT',
            "Removed judge assignment: '{$judgeName}' from project '{$projectTitle}' (Round {$roundNo})"
        );

        return $this->backWithSuccess('Judge assignment has been deleted.');
    }

    /**
     * Rules that apply to the whole round rather than to one pair.
     */
    private function roundGateError(CompetitionSession $session, int $roundNo): ?string
    {
        if ($roundNo === 1) {
            return $this->calculator->isRoundOneLocked($session->id)
                ? 'Round 1 is closed because Round 2 judging has started.'
                : null;
        }

        if ($session->r2_locked) {
            return 'Round 2 assignment is locked because Round 2 session has been closed.';
        }

        $hasRoundOneAssignments = JudgeAssignment::where('session_id', $session->id)
            ->where('round_no', 1)
            ->exists();

        if ($hasRoundOneAssignments && ! $this->calculator->isRoundOneComplete($session->id)) {
            return 'Round 2 assignments are unavailable until all Round 1 evaluations are completed.';
        }

        return null;
    }

    /**
     * Data loaded once per request and reused for every pair.
     *
     * @return array{projects: Collection<int, Project>, judges: Collection<int, User>, assigned: array<string, bool>, shortlisted: array<int, int>|null}
     */
    private function pairContext(CompetitionSession $session, int $roundNo): array
    {
        return [
            'projects' => Project::where('session_id', $session->id)
                ->where('status', Project::STATUS_APPROVED)
                ->get()
                ->keyBy('id'),
            'judges' => User::withRole('judge')->get()->keyBy('id'),
            'assigned' => JudgeAssignment::where('session_id', $session->id)
                ->where('round_no', $roundNo)
                ->get(['project_id', 'judge_id'])
                ->mapWithKeys(fn (JudgeAssignment $assignment) => ["{$assignment->project_id}-{$assignment->judge_id}" => true])
                ->all(),
            'shortlisted' => $roundNo === 2 ? array_flip($this->shortlistedProjectIds($session)) : null,
        ];
    }

    /**
     * Check one project/judge pair against every per-pair rule.
     *
     * @param  array{projects: Collection<int, Project>, judges: Collection<int, User>, assigned: array<string, bool>, shortlisted: array<int, int>|null}  $context
     * @return array{0: Project|null, 1: User|null, 2: string|null}
     */
    private function checkPair(CompetitionSession $session, int $roundNo, int $projectId, int $judgeId, array $context): array
    {
        $project = $context['projects']->get($projectId);
        $judge = $context['judges']->get($judgeId);

        if (! $judge) {
            return [$project, null, 'The selected user is not a Judge.'];
        }

        if (! $project) {
            return [null, $judge, 'Project not found or not approved.'];
        }

        if ($this->calculator->hasConflictOfInterest($project, $judge)) {
            return [$project, $judge, 'This judge has a conflict of interest with the project (owner or supervisor).'];
        }

        if ($roundNo === 2 && ! $this->calculator->isJudgeEligibleForRound2($project->id, $judge->id, $session->id)) {
            return [$project, $judge, 'This judge is not eligible for Round 2 because they were assigned to or evaluated this project in Round 1.'];
        }

        if ($roundNo === 2 && ! isset($context['shortlisted'][$project->id])) {
            return [$project, $judge, 'Round 2 assignments are limited to projects shortlisted from Round 1.'];
        }

        if (isset($context['assigned']["{$project->id}-{$judge->id}"])) {
            return [$project, $judge, 'This judge is already assigned to the selected project for this round.'];
        }

        return [$project, $judge, null];
    }

    /**
     * @param  array<int, array{0: Project, 1: User}>  $pairs
     */
    private function createAssignments(CompetitionSession $session, int $roundNo, array $pairs): int
    {
        foreach ($pairs as [$project, $judge]) {
            JudgeAssignment::create([
                'project_id' => $project->id,
                'judge_id' => $judge->id,
                'session_id' => $session->id,
                'round_no' => $roundNo,
            ]);

            AuditLogger::log(
                'ASSIGN_JUDGE',
                "Assigned judge '{$judge->name}' to project '{$project->title}' for Round {$roundNo}"
            );
        }

        $this->notifyJudges(collect($pairs)->map(fn (array $pair) => $pair[1]), $roundNo);

        return count($pairs);
    }

    /**
     * One notification per judge, however many projects they received.
     *
     * @param  Collection<int, User>  $judges
     */
    private function notifyJudges(Collection $judges, int $roundNo): void
    {
        $judges->groupBy('id')->each(function ($assigned) use ($roundNo) {
            try {
                $count = $assigned->count();
                $assigned->first()->notify(new SystemNotification(
                    $count === 1 ? 'New Judging Assignment' : 'New Judging Assignments',
                    $count === 1
                        ? "You have been assigned a new project to evaluate for Round {$roundNo}."
                        : "You have been assigned {$count} new projects to evaluate for Round {$roundNo}.",
                    '/dashboard/judge/evaluations',
                    'info',
                    mailEvent: 'judge_assigned',
                    mailData: [
                        'projects' => $count === 1 ? '1 project' : "{$count} projects",
                        'round' => $roundNo,
                    ],
                ));
            } catch (\Exception $e) {
                // Keep resilient
            }
        });
    }

    /**
     * @return array<int, int>
     */
    private function shortlistedProjectIds(CompetitionSession $session): array
    {
        // The automatic shortlist plus any Round 2 wildcards.
        return $this->calculator->getRound2ProjectIds($session);
    }

    /**
     * Judges assigned to or scoring each project in Round 1 (ineligible for its Round 2).
     *
     * @return array<int, array<int, int>>
     */
    private function roundOneJudgesByProject(CompetitionSession $session): array
    {
        return JudgeAssignment::where('session_id', $session->id)->where('round_no', 1)->get(['project_id', 'judge_id'])
            ->concat(Score::where('session_id', $session->id)->where('round_no', 1)->get(['project_id', 'judge_id']))
            ->groupBy('project_id')
            ->map(fn ($rows) => $rows->pluck('judge_id')->map(fn ($id) => (int) $id)->unique()->values()->all())
            ->all();
    }
}
