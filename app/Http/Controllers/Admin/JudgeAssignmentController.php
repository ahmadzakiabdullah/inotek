<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompetitionSession;
use App\Models\Project;
use App\Models\User;
use App\Models\JudgeAssignment;
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
        $activeSession = CompetitionSession::where('is_active', true)->first();

        if (!$activeSession) {
            return Inertia::render('admin/assignments/Index', [
                'projects' => [],
                'judges' => [],
                'assignments' => [],
                'activeSession' => null,
            ]);
        }

        // Fetch judges
        $judges = User::where('role_id', 3)->orderBy('name')->get(['id', 'name', 'email']);

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

        return Inertia::render('admin/assignments/Index', [
            'projects' => $projects,
            'judges' => $judges,
            'assignments' => $assignments,
            'activeSession' => $activeSession,
        ]);
    }

    /**
     * Assign a judge to a project.
     */
    public function store(Request $request): RedirectResponse
    {
        $activeSession = CompetitionSession::where('is_active', true)->first();

        if (!$activeSession) {
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
        if (!$user || $user->role_id !== 3) {
            return back()->with('error', 'The selected user is not a Judge.');
        }

        // Verify project belongs to active session and is approved
        $project = Project::where('id', $projectId)
            ->where('session_id', $activeSession->id)
            ->where('status', Project::STATUS_APPROVED)
            ->first();

        if (!$project) {
            return back()->with('error', 'Project not found or not approved.');
        }

        // Check Round 2 assignment eligibility (conflict check)
        if ($roundNo === 2) {
            if ($activeSession->r2_locked) {
                return back()->with('error', 'Round 2 assignment is locked because Round 2 session has been closed.');
            }

            if (!$this->calculator->isJudgeEligibleForRound2($projectId, $judgeId, $activeSession->id)) {
                return back()->with('error', 'This judge is not eligible for Round 2 because they evaluated this project in Round 1.');
            }
        }

        // Create assignment
        try {
            JudgeAssignment::updateOrCreate(
                [
                    'project_id' => $projectId,
                    'judge_id' => $judgeId,
                    'session_id' => $activeSession->id,
                    'round_no' => $roundNo,
                ]
            );

            \App\Services\AuditLogger::log(
                'ASSIGN_JUDGE',
                "Assigned judge '{$user->name}' to project '{$project->title}' for Round {$roundNo}"
            );

            // Notify Judge
            try {
                $user->notify(new \App\Notifications\SystemNotification(
                    'New Judging Assignment',
                    "You have been assigned to evaluate the project '{$project->title}' for Round {$roundNo}.",
                    '/judge/evaluations',
                    'info'
                ));
            } catch (\Exception $e) {
                // Keep resilient
            }
        } catch (\Exception $e) {
            return back()->with('error', 'Error while assigning judge: ' . $e->getMessage());
        }

        return back()->with('success', 'Judge has been assigned successfully.');
    }

    /**
     * Remove a judge assignment.
     */
    public function destroy(JudgeAssignment $assignment): RedirectResponse
    {
        $activeSession = CompetitionSession::where('is_active', true)->first();

        if (!$activeSession || $assignment->session_id !== $activeSession->id) {
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

        \App\Services\AuditLogger::log(
            'REMOVE_JUDGE_ASSIGNMENT',
            "Removed judge assignment: '{$judgeName}' from project '{$projectTitle}' (Round {$roundNo})"
        );

        return back()->with('success', 'Judge assignment has been deleted.');
    }
}
