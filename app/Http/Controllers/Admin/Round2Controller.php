<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompetitionSession;
use App\Models\JudgeAssignment;
use App\Models\Project;
use App\Services\AuditLogger;
use App\Services\ScoreCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Round2Controller extends Controller
{
    protected ScoreCalculator $calculator;

    public function __construct(ScoreCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    /**
     * Display Round 2 shortlisted projects and settings.
     */
    public function index(): Response
    {
        $activeSession = CompetitionSession::active();

        if (! $activeSession) {
            return Inertia::render('admin/round2/Index', [
                'categories' => [],
                'shortlist' => [],
                'activeSession' => null,
                'limit' => 3,
            ]);
        }

        $limit = $activeSession->round2_shortlist_limit ?: 3;

        // Get Round 1 average score results and shortlist Top N projects per category
        $shortlist = $this->calculator->getRound1Shortlist($activeSession->id, $limit);

        // Fetch Round 2 project scores to compare/view final evaluations
        $round2Scores = Project::where('projects.session_id', $activeSession->id)
            ->where('projects.status', Project::STATUS_APPROVED)
            ->join('scores', 'projects.id', '=', 'scores.project_id')
            ->where('scores.round_no', 2)
            ->with(['category'])
            ->select('projects.*', 'scores.total as round2_total', 'scores.judge_id', 'scores.comments')
            ->get();

        return Inertia::render('admin/round2/Index', [
            'shortlist' => $shortlist,
            'round2Scores' => $round2Scores,
            'activeSession' => $activeSession,
            'limit' => $limit,
        ]);
    }

    public function updateShortlistLimit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'limit' => ['required', 'integer', 'in:3,5,10'],
        ]);
        $activeSession = CompetitionSession::active();

        if (! $activeSession) {
            return back()->with('error', 'No active competition session.');
        }

        $shortlistedProjectIds = $this->calculator->getRound1Shortlist(
            $activeSession->id,
            $validated['limit'],
        )->flatten()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $existingRoundTwoProjectIds = JudgeAssignment::where('session_id', $activeSession->id)
            ->where('round_no', 2)
            ->distinct()
            ->pluck('project_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (array_diff($existingRoundTwoProjectIds, $shortlistedProjectIds) !== []) {
            return back()->with('error', 'Remove Round 2 assignments outside the new shortlist before changing its size.');
        }

        $activeSession->round2_shortlist_limit = $validated['limit'];
        $activeSession->save();

        return back()->with('success', 'Round 2 shortlist size updated.');
    }

    /**
     * Finalize or reopen the Round 2 judging state.
     */
    public function lockToggle(): RedirectResponse
    {
        $activeSession = CompetitionSession::active();

        if (! $activeSession) {
            return back()->with('error', 'No active competition session.');
        }

        $activeSession->r2_locked = ! $activeSession->r2_locked;
        $activeSession->save();

        // Keep the historical action names for audit-log compatibility while
        // presenting the clearer "Finalize" terminology in the UI.
        $action = $activeSession->r2_locked ? 'LOCK_ROUND_2' : 'UNLOCK_ROUND_2';
        $description = $activeSession->r2_locked
            ? "Finalized Round 2 judging for session: '{$activeSession->name}'"
            : "Reopened Round 2 judging for session: '{$activeSession->name}'";

        AuditLogger::log($action, $description);

        $statusMessage = $activeSession->r2_locked
            ? 'Round 2 judging has been finalized successfully.'
            : 'Round 2 judging has been reopened successfully.';

        return back()->with('success', $statusMessage);
    }

    /**
     * Assign or update an award level to a project (Gold, Silver, Bronze, None).
     */
    public function assignAward(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'award_level' => ['required', 'string', 'in:Gold,Silver,Bronze,None'],
        ]);

        $activeSession = CompetitionSession::active();
        if (! $activeSession || $project->session_id !== $activeSession->id || $project->status !== Project::STATUS_APPROVED) {
            abort(404);
        }

        $shortlistedProjectIds = $this->calculator->getRound1Shortlist(
            $activeSession->id,
            $activeSession->round2_shortlist_limit ?: 3,
        )->flatten()->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (! in_array($project->id, $shortlistedProjectIds, true)) {
            return back()->with('error', 'Awards can only be assigned to projects shortlisted for Round 2.');
        }

        if ($validated['award_level'] !== 'None' && ! $project->scores()
            ->where('session_id', $activeSession->id)
            ->where('round_no', 2)
            ->exists()) {
            return back()->with('error', 'An award can only be assigned after the project has a Round 2 evaluation.');
        }

        $award = $validated['award_level'] === 'None' ? null : $validated['award_level'];
        $project->update([
            'award_level' => $award,
        ]);

        AuditLogger::log(
            'ASSIGN_AWARD',
            "Assigned award '".($award ?? 'None')."' to project '{$project->title}'"
        );

        return back()->with('success', 'Award assigned successfully.');
    }
}
