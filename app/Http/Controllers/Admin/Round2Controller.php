<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\JudgeAssignment;
use App\Models\Project;
use App\Models\Round2Wildcard;
use App\Models\Score;
use App\Services\AuditLogger;
use App\Services\CompetitionNotifier;
use App\Services\Round2Wildcards;
use App\Services\ScoreCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class Round2Controller extends Controller
{
    protected ScoreCalculator $calculator;

    public function __construct(ScoreCalculator $calculator, private readonly Round2Wildcards $wildcards)
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
                'wildcards' => [],
                'wildcardCandidates' => [],
                'wildcardLimit' => Round2Wildcards::limit(),
                'scoringStartedCategoryIds' => [],
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

        // Wildcards are shown only on this admin page and to admins on the leaderboard.
        $roundOneAverages = Score::where('session_id', $activeSession->id)
            ->where('round_no', 1)
            ->groupBy('project_id')
            ->pluck(DB::raw('AVG(total)'), 'project_id');
        $wildcards = $this->wildcards->forSession($activeSession->id)->map(fn (Round2Wildcard $wildcard) => [
            'project_id' => $wildcard->project_id,
            'avg_r1' => isset($roundOneAverages[$wildcard->project_id]) ? round((float) $roundOneAverages[$wildcard->project_id], 2) : null,
            'pcode' => $wildcard->project?->pcode,
            'title' => $wildcard->project?->title,
            'category_id' => $wildcard->project?->category_id,
            'reason' => $wildcard->reason,
            'added_by' => $wildcard->addedBy?->name,
            'added_at' => $wildcard->created_at?->toDateTimeString(),
        ])->values();
        $categories = Category::orderBy('code')->get(['id', 'code', 'name']);

        return Inertia::render('admin/round2/Index', [
            'categories' => $categories,
            'shortlist' => $shortlist,
            'round2Scores' => $round2Scores,
            'activeSession' => $activeSession,
            'limit' => $limit,
            'wildcards' => $wildcards,
            'wildcardCandidates' => $this->wildcards->candidates($activeSession),
            'wildcardLimit' => Round2Wildcards::limit(),
            'shortlistTies' => (object) $this->calculator->shortlistBoundaryTies($activeSession->id, $limit),
            'scoringStartedCategoryIds' => $categories
                ->filter(fn (Category $category) => $this->wildcards->roundTwoScoringStarted($activeSession->id, $category->id))
                ->pluck('id')
                ->values(),
        ]);
    }

    /**
     * Add a Best Presenter nominee to Round 2 as a wildcard.
     */
    public function addWildcard(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $activeSession = CompetitionSession::active();

        if (! $activeSession) {
            return $this->backWithError('No active competition session.');
        }

        $error = $this->wildcards->add($activeSession, $project, trim($validated['reason']), $request->user());

        return $error === null
            ? $this->backWithSuccess("Added {$project->pcode} to Round 2 as a wildcard.")
            : $this->backWithError($error);
    }

    /**
     * Take a wildcard back out of Round 2.
     */
    public function removeWildcard(Project $project): RedirectResponse
    {
        $activeSession = CompetitionSession::active();

        if (! $activeSession) {
            return $this->backWithError('No active competition session.');
        }

        $error = $this->wildcards->remove($activeSession, $project);

        return $error === null
            ? $this->backWithSuccess("Removed {$project->pcode} from Round 2.")
            : $this->backWithError($error);
    }

    public function updateShortlistLimit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'limit' => ['required', 'integer', 'in:3,5,10'],
        ]);
        $activeSession = CompetitionSession::active();

        if (! $activeSession) {
            return $this->backWithError('No active competition session.');
        }

        // Wildcards stay in Round 2 whatever the shortlist size.
        $shortlistedProjectIds = $this->calculator->getRound1Shortlist(
            $activeSession->id,
            $validated['limit'],
        )->flatten()->pluck('id')
            ->merge($this->wildcards->forSession($activeSession->id)->pluck('project_id'))
            ->map(fn ($id) => (int) $id)->all();
        $existingRoundTwoProjectIds = JudgeAssignment::where('session_id', $activeSession->id)
            ->where('round_no', 2)
            ->distinct()
            ->pluck('project_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (array_diff($existingRoundTwoProjectIds, $shortlistedProjectIds) !== []) {
            return $this->backWithError('Remove Round 2 assignments outside the new shortlist before changing its size.');
        }

        $activeSession->round2_shortlist_limit = $validated['limit'];
        $activeSession->save();

        return $this->backWithSuccess('Round 2 shortlist size updated.');
    }

    /**
     * Finalize or reopen the Round 2 judging state.
     */
    public function lockToggle(Request $request): RedirectResponse
    {
        $activeSession = CompetitionSession::active();

        if (! $activeSession) {
            return $this->backWithError('No active competition session.');
        }

        // The page sends the state it wants. Without it a double submit would
        // finalize and then immediately reopen the results.
        $validated = $request->validate(['locked' => ['sometimes', 'boolean']]);
        $targetLocked = array_key_exists('locked', $validated)
            ? (bool) $validated['locked']
            : ! $activeSession->r2_locked;

        if ($targetLocked === (bool) $activeSession->r2_locked) {
            return $this->backWithSuccess($targetLocked
                ? 'Round 2 judging is already finalized.'
                : 'Round 2 judging is already open.');
        }

        $activeSession->r2_locked = $targetLocked;
        $activeSession->save();

        // Keep the historical action names for audit-log compatibility while
        // presenting the clearer "Finalize" terminology in the UI.
        $action = $activeSession->r2_locked ? 'LOCK_ROUND_2' : 'UNLOCK_ROUND_2';
        $description = $activeSession->r2_locked
            ? "Finalized Round 2 judging for session: '{$activeSession->name}'"
            : "Reopened Round 2 judging for session: '{$activeSession->name}'";

        AuditLogger::log($action, $description);

        if ($activeSession->r2_locked) {
            try {
                app(CompetitionNotifier::class)->resultsPublished($activeSession);
            } catch (\Exception $e) {
                report($e);
            }
        }

        $statusMessage = $activeSession->r2_locked
            ? 'Round 2 judging has been finalized successfully.'
            : 'Round 2 judging has been reopened successfully.';

        return $this->backWithSuccess($statusMessage);
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

        // Wildcards compete for awards exactly like shortlisted finalists.
        $shortlistedProjectIds = $this->calculator->getRound2ProjectIds($activeSession);

        if (! in_array($project->id, $shortlistedProjectIds, true)) {
            return $this->backWithError('Awards can only be assigned to projects shortlisted for Round 2.');
        }

        if ($validated['award_level'] !== 'None' && ! $project->scores()
            ->where('session_id', $activeSession->id)
            ->where('round_no', 2)
            ->exists()) {
            return $this->backWithError('An award can only be assigned after the project has a Round 2 evaluation.');
        }

        $award = $validated['award_level'] === 'None' ? null : $validated['award_level'];
        $project->update([
            'award_level' => $award,
        ]);

        AuditLogger::log(
            'ASSIGN_AWARD',
            "Assigned award '".($award ?? 'None')."' to project '{$project->title}'"
        );

        return $this->backWithSuccess('Award assigned successfully.');
    }
}
