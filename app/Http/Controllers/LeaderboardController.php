<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\Project;
use App\Models\Round2Wildcard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    /**
     * Display the public leaderboard, or the staff leaderboard for any session.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $isStaffLeaderboard = $request->routeIs('dashboard.leaderboard');
        // The public board always shows the active session. Staff can open any
        // session, so past results stay reachable after a new session starts;
        // without a choice they get the active session, or the newest one.
        $activeSession = match (true) {
            $isStaffLeaderboard && $request->filled('session') => CompetitionSession::find($request->integer('session')),
            $isStaffLeaderboard => CompetitionSession::active() ?? CompetitionSession::orderByDesc('id')->first(),
            default => CompetitionSession::active(),
        };
        $sessions = $isStaffLeaderboard
            ? CompetitionSession::orderByDesc('id')->get(['id', 'name', 'is_active', 'r2_locked'])
            : collect();

        if (! $activeSession) {
            if ($request->wantsJson() || $request->input('api') === 'true') {
                return response()->json([
                    'activeSession' => null,
                    'categories' => [],
                    'leaderboardData' => [],
                ]);
            }

            $page = $request->routeIs('dashboard.leaderboard')
                ? 'leaderboard/App'
                : 'leaderboard/Index';

            return Inertia::render($page, [
                'activeSession' => null,
                'categories' => [],
                'leaderboardData' => [],
                'resultsPublished' => false,
                'resultsFinal' => false,
                'sessions' => $sessions,
            ]);
        }

        $sessionCategoryIds = Project::where('session_id', $activeSession->id)
            ->select('category_id')
            ->distinct();
        $categories = Category::whereIn('id', $sessionCategoryIds)
            ->orderBy('code')
            ->get();

        // Retrieve projects with their average Round 1, Round 2 scores and count of judges
        $projects = Project::where('session_id', $activeSession->id)
            ->where('status', Project::STATUS_APPROVED)
            ->with(['user', 'category'])
            ->get();

        $isPublicResultsPublished = (bool) $activeSession->r2_locked;
        // Scores, awards and judge comments are shown on the staff leaderboard to
        // admins always, and to everyone else only once results are finalized.
        $user = $request->user();
        $isAuthenticatedLeaderboard = $isStaffLeaderboard
            && ($isPublicResultsPublished || $user?->hasRole('admin'));

        // Before results are finalized a judge sees only their own evaluations, so
        // other judges' marks and comments cannot influence their scoring.
        $ownScoresJudgeId = ($isStaffLeaderboard && ! $isAuthenticatedLeaderboard && $user?->hasRole('judge'))
            ? $user->id
            : null;

        // Keep judging details restricted to the authenticated staff leaderboard.
        $r1Averages = collect();
        $r2Averages = collect();
        $scoreDetails = collect();
        if ($isAuthenticatedLeaderboard || $isPublicResultsPublished) {
            $r1Averages = DB::table('scores')
                ->where('session_id', $activeSession->id)
                ->where('round_no', 1)
                ->select('project_id', DB::raw('AVG(total) as avg_r1'), DB::raw('COUNT(id) as judges_r1'))
                ->groupBy('project_id')
                ->get()
                ->keyBy('project_id');

            $r2Averages = DB::table('scores')
                ->where('session_id', $activeSession->id)
                ->where('round_no', 2)
                ->select('project_id', DB::raw('AVG(total) as avg_r2'), DB::raw('COUNT(id) as judges_r2'))
                ->groupBy('project_id')
                ->get()
                ->keyBy('project_id');
        }

        if ($isAuthenticatedLeaderboard || $ownScoresJudgeId !== null) {
            $scoreDetails = DB::table('scores')
                ->join('users', 'users.id', '=', 'scores.judge_id')
                ->where('scores.session_id', $activeSession->id)
                ->when($ownScoresJudgeId !== null, fn ($query) => $query->where('scores.judge_id', $ownScoresJudgeId))
                ->select('scores.project_id', 'scores.round_no', 'scores.total', 'scores.best_presenter', 'scores.comments', 'scores.created_at', 'users.name as judge_name', 'users.username as judge_username')
                // Accounts carried over from the legacy system keep the full name in `nama`; `name` holds the username.
                ->when(Schema::hasColumn('users', 'nama'), fn ($query) => $query->addSelect('users.nama as judge_full_name'))
                ->orderBy('scores.round_no')
                ->get()
                ->groupBy('project_id');
        }

        // Round 2 wildcards are labelled for admins only; everyone else sees a
        // wildcard as an ordinary Round 2 finalist.
        $wildcardIds = ($isStaffLeaderboard && $user?->hasRole('admin'))
            ? Round2Wildcard::where('session_id', $activeSession->id)->pluck('project_id')->flip()
            : null;

        $leaderboardData = $projects->map(function ($project) use ($r1Averages, $r2Averages, $scoreDetails, $isAuthenticatedLeaderboard, $isPublicResultsPublished, $ownScoresJudgeId, $wildcardIds) {
            $r1 = $r1Averages->get($project->id);
            $r2 = $r2Averages->get($project->id);

            $avgR1 = $r1 ? round($r1->avg_r1, 2) : 0.0;
            $avgR2 = $r2 ? round($r2->avg_r2, 2) : 0.0;

            // Presence of a Round 2 score, not its numeric value, determines priority.
            $finalScore = $r2 ? $avgR2 : $avgR1;

            $projectScores = $scoreDetails->get($project->id, collect());

            $judges = ($isAuthenticatedLeaderboard || $ownScoresJudgeId !== null) ? $projectScores->map(fn ($score) => [
                'name' => $this->judgeFullName($score),
                'username' => $score->judge_username,
                'round' => (int) $score->round_no,
                'score' => round((float) $score->total, 2),
                'comments' => $score->comments,
                'best_presenter' => $this->presenterName($score->best_presenter, $project),
                'submitted_at' => $score->created_at,
            ])->values()->all() : [];

            $presenterVotes = $isAuthenticatedLeaderboard ? $projectScores
                ->map(fn ($score) => $this->presenterName($score->best_presenter, $project))
                ->filter()
                ->countBy()
                ->sortDesc()
                ->map(fn ($votes, $name) => ['name' => $name, 'votes' => $votes])
                ->values()->first() : null;

            return [
                'id' => $project->id,
                'title' => $project->title,
                'pcode' => $project->pcode,
                'category_id' => $project->category_id,
                'category_name' => $project->category->name ?? 'N/A',
                ...($isAuthenticatedLeaderboard ? ['username' => $project->user?->name] : []),
                // Awards stay confidential on the public board until results are finalized.
                'award_level' => ($isAuthenticatedLeaderboard || $isPublicResultsPublished) ? $project->award_level : null,
                ...(($isAuthenticatedLeaderboard || $isPublicResultsPublished) ? [
                    'avg_r1' => $avgR1,
                    'judges_r1' => $r1 ? $r1->judges_r1 : 0,
                    'avg_r2' => $avgR2,
                    'judges_r2' => $r2 ? $r2->judges_r2 : 0,
                    'final_score' => $finalScore,
                ] : []),
                ...($isAuthenticatedLeaderboard ? [
                    'judges' => $judges,
                    'best_presenter' => $presenterVotes,
                ] : []),
                ...($ownScoresJudgeId !== null ? ['judges' => $judges] : []),
                ...($wildcardIds !== null ? ['round2_wildcard' => $wildcardIds->has($project->id)] : []),
            ];
        });

        // Group by category and sort by final_score descending
        $groupedData = [];
        foreach ($categories as $cat) {
            $catProjects = $leaderboardData->filter(function ($item) use ($cat) {
                return $item['category_id'] === $cat->id;
            });
            if ($isAuthenticatedLeaderboard || $isPublicResultsPublished) {
                // Round 2 finalists always rank above projects judged only in Round 1.
                $catProjects = $catProjects->sortBy([
                    fn ($a, $b) => ($b['judges_r2'] > 0) <=> ($a['judges_r2'] > 0),
                    fn ($a, $b) => $b['final_score'] <=> $a['final_score'],
                ]);
            } else {
                $catProjects = $catProjects->sortBy('pcode');
            }
            $groupedData[$cat->id] = $catProjects->values()->all();
        }

        // Return API response if request wants JSON (for real-time polling fallback)
        if ($request->wantsJson() || $request->input('api') === 'true') {
            return response()->json([
                'activeSession' => $activeSession,
                'categories' => $categories,
                'leaderboardData' => $groupedData,
            ]);
        }

        $page = $request->routeIs('dashboard.leaderboard')
            ? 'leaderboard/App'
            : 'leaderboard/Index';

        return Inertia::render($page, [
            'activeSession' => $activeSession,
            'categories' => $categories,
            'leaderboardData' => $groupedData,
            'resultsPublished' => $isAuthenticatedLeaderboard || $isPublicResultsPublished,
            // Admins see scores before Round 2 is locked; this tells the page they are still a draft.
            'resultsFinal' => $isPublicResultsPublished,
            'sessions' => $sessions,
        ]);
    }

    /**
     * The judge's full name: the legacy `nama` column when filled, otherwise `name`.
     */
    private function judgeFullName(\stdClass $score): string
    {
        $fullName = trim((string) ($score->judge_full_name ?? ''));

        return $fullName !== '' ? $fullName : (string) $score->judge_name;
    }

    /**
     * Turn a stored best presenter nomination into the nominee's name. Older
     * evaluations store a plain yes/no flag instead of a name; a "yes" there
     * nominates the project's registered participant.
     */
    private function presenterName(?string $value, Project $project): ?string
    {
        $value = trim((string) $value);

        if (in_array(strtolower($value), ['', '0', 'no', 'n', 'tidak', 'false'], true)) {
            return null;
        }

        if (in_array(strtolower($value), ['1', 'yes', 'y', 'ya', 'true'], true)) {
            return $project->user->name ?? 'Project presenter';
        }

        return $value;
    }
}
