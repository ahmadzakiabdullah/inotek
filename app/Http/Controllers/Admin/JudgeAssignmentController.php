<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompetitionSession;
use App\Models\JudgeAssignment;
use App\Models\Project;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\AuditLogger;
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
        $assignments = JudgeAssignment::with(['project.category', 'judge'])
            ->where('session_id', $activeSession->id)
            ->get();

        $roundOneComplete = $this->calculator->isRoundOneComplete($activeSession->id);

        return Inertia::render('admin/assignments/Index', [
            'projects' => $projects,
            'judges' => $judges,
            'assignments' => $assignments,
            'activeSession' => $activeSession,
            'roundOneComplete' => $roundOneComplete,
        ]);
    }

    /**
     * Assign a judge to a project.
     */
    public function store(Request $request): RedirectResponse
    {
        $activeSession = CompetitionSession::active();

        if (! $activeSession) {
            return back()->with('error', 'No active competition session.');
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
            return back()->with('error', 'The selected user is not a Judge.');
        }

        // Verify project belongs to active session and is approved
        $project = Project::where('id', $projectId)
            ->where('session_id', $activeSession->id)
            ->where('status', Project::STATUS_APPROVED)
            ->first();

        if (! $project) {
            return back()->with('error', 'Project not found or not approved.');
        }

        if ($this->calculator->hasConflictOfInterest($project, $user)) {
            return back()->with('error', 'This judge has a conflict of interest with the project (owner or supervisor).');
        }

        if ($roundNo === 1 && $this->calculator->isRoundOneLocked($activeSession->id)) {
            return back()->with('error', 'Round 1 is closed because Round 2 judging has started.');
        }

        // Check conflicts before the session-wide Round 1 completion gate so
        // the administrator receives the specific eligibility explanation.
        if ($roundNo === 2 && ! $this->calculator->isJudgeEligibleForRound2($projectId, $judgeId, $activeSession->id)) {
            return back()->with('error', 'This judge is not eligible for Round 2 because they evaluated this project in Round 1.');
        }

        if ($roundNo === 2) {
            $shortlist = $this->calculator->getRound1Shortlist(
                $activeSession->id,
                $activeSession->round2_shortlist_limit ?: 3,
            );
            $shortlistedProjectIds = $shortlist->flatten()->pluck('id')->map(fn ($id) => (int) $id)->all();

            if (! in_array($projectId, $shortlistedProjectIds, true)) {
                return back()->with('error', 'Round 2 assignments are limited to projects shortlisted from Round 1.');
            }

            $hasRoundOneAssignments = JudgeAssignment::where('session_id', $activeSession->id)
                ->where('round_no', 1)
                ->exists();

            if ($hasRoundOneAssignments && ! $this->calculator->isRoundOneComplete($activeSession->id)) {
                return back()->with('error', 'Round 2 assignments are unavailable until all Round 1 evaluations are completed.');
            }
        }

        $alreadyAssigned = JudgeAssignment::where('project_id', $projectId)
            ->where('judge_id', $judgeId)
            ->where('session_id', $activeSession->id)
            ->where('round_no', $roundNo)
            ->exists();

        if ($alreadyAssigned) {
            return back()->with('error', 'This judge is already assigned to the selected project for this round.');
        }

        if ($roundNo === 2 && $activeSession->r2_locked) {
            return back()->with('error', 'Round 2 assignment is locked because Round 2 session has been closed.');
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

            return back()->with('error', 'The judge could not be assigned. Please try again or contact the system administrator.');
        }

        return back()->with('success', 'Judge has been assigned successfully.');
    }

    /**
     * Remove a judge assignment.
     */
    public function destroy(JudgeAssignment $assignment): RedirectResponse
    {
        $activeSession = CompetitionSession::active();

        if (! $activeSession || $assignment->session_id !== $activeSession->id) {
            return back()->with('error', 'Operation not allowed.');
        }

        // If Round 2 assignment and locked, block deletion
        if ($assignment->round_no === 2 && $activeSession->r2_locked) {
            return back()->with('error', 'Round 2 assignment is locked and cannot be deleted.');
        }

        $judgeName = $assignment->judge->name ?? 'Unknown Judge';
        $projectTitle = $assignment->project->title ?? 'Unknown Project';
        $roundNo = $assignment->round_no;

        $assignment->delete();

        AuditLogger::log(
            'REMOVE_JUDGE_ASSIGNMENT',
            "Removed judge assignment: '{$judgeName}' from project '{$projectTitle}' (Round {$roundNo})"
        );

        return back()->with('success', 'Judge assignment has been deleted.');
    }
}
