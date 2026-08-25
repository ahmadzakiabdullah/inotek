<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompetitionSession;
use App\Models\Project;
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
    public function index(Request $request): Response
    {
        $activeSession = CompetitionSession::where('is_active', true)->first();

        if (!$activeSession) {
            return Inertia::render('admin/round2/Index', [
                'categories' => [],
                'shortlist' => [],
                'activeSession' => null,
                'limit' => 3,
            ]);
        }

        $limit = (int) $request->input('limit', 3);
        if ($limit < 1 || $limit > 10) {
            $limit = 3;
        }

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

    /**
     * Finalize or reopen the Round 2 judging state.
     */
    public function lockToggle(): RedirectResponse
    {
        $activeSession = CompetitionSession::where('is_active', true)->first();

        if (!$activeSession) {
            return back()->with('error', 'No active competition session.');
        }

        $activeSession->r2_locked = !$activeSession->r2_locked;
        $activeSession->save();

        $action = $activeSession->r2_locked ? 'FINALIZE_ROUND_2' : 'REOPEN_ROUND_2';
        $description = $activeSession->r2_locked
            ? "Finalized Round 2 judging for session: '{$activeSession->name}'"
            : "Reopened Round 2 judging for session: '{$activeSession->name}'";

        \App\Services\AuditLogger::log($action, $description);

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
            'award_level' => ['nullable', 'string', 'in:Gold,Silver,Bronze,None'],
        ]);

        $award = $validated['award_level'] === 'None' ? null : $validated['award_level'];
        $project->update([
            'award_level' => $award,
        ]);

        \App\Services\AuditLogger::log(
            'ASSIGN_AWARD',
            "Assigned award '" . ($award ?? 'None') . "' to project '{$project->title}'"
        );

        return back()->with('success', 'Award assigned successfully.');
    }
}
