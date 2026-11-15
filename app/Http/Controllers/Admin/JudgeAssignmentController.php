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
use Inertia\Inertia;
use Inertia\Response;

class JudgeAssignmentController extends Controller
{
    protected ScoreCalculator $calculator;

    public function __construct(ScoreCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    /**
     * Display a list of projects and their judge assignments.
     */
    public function index(): Response
    {
        $activeSession = CompetitionSession::active();

        if (! $activeSession) {
            return Inertia::render('admin/assignments/Index', [
                'projects' => [],
                'judges' => [],
                'assignments' => [],
                'activeSession' => null,
                'roundOneComplete' => false,
                'autoAssign' => [
                    'enabled' => JudgeAutoAssigner::isEnabled(),
                    'judgesPerProject' => JudgeAutoAssigner::judgesPerProject(),
                    'max' => JudgeAutoAssigner::MAX_JUDGES_PER_PROJECT,
                ],
            ]);
        }

        // Fetch judges
        $judges = User::withRole('judge')->orderBy('name')
            ->get(['id', 'name', 'email']);

        // Fetch approved projects in active session
        $projects = Project::where('session_id', $activeSession->id)
            ->where('status', Project::STATUS_APPROVED)
            ->with(['category', 'judgeAssignments.judge'])
            ->orderBy('title')
            ->get();

        // Get all assignments for the active session
        // Skip assignments whose project or judge has been deleted; the page
        // reads both relations.
        $assignments = JudgeAssignment::with(['project.category', 'judge'])
            ->where('session_id', $activeSession->id)
            ->whereHas('project')
            ->whereHas('judge')
            ->get();

        $roundOneComplete = $this->calculator->isRoundOneComplete($activeSession->id);

        return Inertia::render('admin/assignments/Index', [
            'projects' => $projects,
            'judges' => $judges,
            'assignments' => $assignments,
            'activeSession' => $activeSession,
            'roundOneComplete' => $roundOneComplete,
            'autoAssign' => [
                'enabled' => JudgeAutoAssigner::isEnabled(),
                'judgesPerProject' => JudgeAutoAssigner::judgesPerProject(),
                'max' => JudgeAutoAssigner::MAX_JUDGES_PER_PROJECT,
            ],
        ]);
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

        if ($roundNo === 1 && $this->calculator->isRoundOneLocked($activeSession->id)) {
            return $this->backWithError('Round 1 is closed because Round 2 judging has started.');
        }

        if ($roundNo === 2) {
            if ($activeSession->r2_locked) {
                return $this->backWithError('Round 2 assignment is locked because Round 2 session has been closed.');
            }

            $hasRoundOneAssignments = JudgeAssignment::where('session_id', $activeSession->id)
                ->where('round_no', 1)
                ->exists();

            if ($hasRoundOneAssignments && ! $this->calculator->isRoundOneComplete($activeSession->id)) {
                return $this->backWithError('Round 2 assignments are unavailable until all Round 1 evaluations are completed.');
            }
        }

        $result = $assigner->assign($activeSession, $roundNo, (int) $validated['judges_per_project']);
        $createdCount = count($result['created']);

        AuditLogger::log(
            'AUTO_ASSIGN_JUDGES',
            "Auto-assigned {$createdCount} judge assignment(s) for Round {$roundNo} ({$validated['judges_per_project']} judges per project, {$result['projects_considered']} project(s))."
        );

        // One notification per judge rather than one per project.
        collect($result['created'])
            ->groupBy(fn (array $item) => $item['judge']->id)
            ->each(function ($items) use ($roundNo) {
                try {
                    $count = $items->count();
                    $items->first()['judge']->notify(new SystemNotification(
                        'New Judging Assignments',
                        "You have been assigned {$count} new project(s) to evaluate for Round {$roundNo}.",
                        '/dashboard/judge/evaluations',
                        'info'
                    ));
                } catch (\Exception $e) {
                    // Keep resilient
                }
            });

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
     * Assign a judge to a project.
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

        $projectId = (int) $validated['project_id'];
        $judgeId = (int) $validated['judge_id'];
        $roundNo = (int) $validated['round_no'];

        // Verify user is a judge
        $user = User::find($judgeId);
        if (! $user || ! $user->hasRole('judge')) {
            return $this->backWithError('The selected user is not a Judge.');
        }

        // Verify project belongs to active session and is approved
        $project = Project::where('id', $projectId)
            ->where('session_id', $activeSession->id)
            ->where('status', Project::STATUS_APPROVED)
            ->first();

        if (! $project) {
            return $this->backWithError('Project not found or not approved.');
        }

        if ($this->calculator->hasConflictOfInterest($project, $user)) {
            return $this->backWithError('This judge has a conflict of interest with the project (owner or supervisor).');
        }

        if ($roundNo === 1 && $this->calculator->isRoundOneLocked($activeSession->id)) {
            return $this->backWithError('Round 1 is closed because Round 2 judging has started.');
        }

        // Check conflicts before the session-wide Round 1 completion gate so
        // the administrator receives the specific eligibility explanation.
        if ($roundNo === 2 && ! $this->calculator->isJudgeEligibleForRound2($projectId, $judgeId, $activeSession->id)) {
            return $this->backWithError('This judge is not eligible for Round 2 because they evaluated this project in Round 1.');
        }

        if ($roundNo === 2) {
            $shortlist = $this->calculator->getRound1Shortlist(
                $activeSession->id,
                $activeSession->round2_shortlist_limit ?: 3,
            );
            $shortlistedProjectIds = $shortlist->flatten()->pluck('id')->map(fn ($id) => (int) $id)->all();

            if (! in_array($projectId, $shortlistedProjectIds, true)) {
                return $this->backWithError('Round 2 assignments are limited to projects shortlisted from Round 1.');
            }

            $hasRoundOneAssignments = JudgeAssignment::where('session_id', $activeSession->id)
                ->where('round_no', 1)
                ->exists();

            if ($hasRoundOneAssignments && ! $this->calculator->isRoundOneComplete($activeSession->id)) {
                return $this->backWithError('Round 2 assignments are unavailable until all Round 1 evaluations are completed.');
            }
        }

        $alreadyAssigned = JudgeAssignment::where('project_id', $projectId)
            ->where('judge_id', $judgeId)
            ->where('session_id', $activeSession->id)
            ->where('round_no', $roundNo)
            ->exists();

        if ($alreadyAssigned) {
            return $this->backWithError('This judge is already assigned to the selected project for this round.');
        }

        if ($roundNo === 2 && $activeSession->r2_locked) {
            return $this->backWithError('Round 2 assignment is locked because Round 2 session has been closed.');
        }

        // Create assignment
        try {
            JudgeAssignment::create([
                'project_id' => $projectId,
                'judge_id' => $judgeId,
                'session_id' => $activeSession->id,
                'round_no' => $roundNo,
            ]);

            AuditLogger::log(
                'ASSIGN_JUDGE',
                "Assigned judge '{$user->name}' to project '{$project->title}' for Round {$roundNo}"
            );

            // Notify Judge
            try {
                $user->notify(new SystemNotification(
                    'New Judging Assignment',
                    "You have been assigned to evaluate the project '{$project->title}' for Round {$roundNo}.",
                    '/dashboard/judge/evaluations',
                    'info'
                ));
            } catch (\Exception $e) {
                // Keep resilient
            }
        } catch (\Exception $e) {
            report($e);

            return $this->backWithError('The judge could not be assigned. Please try again or contact the system administrator.');
        }

        return $this->backWithSuccess('Judge has been assigned successfully.');
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
}
