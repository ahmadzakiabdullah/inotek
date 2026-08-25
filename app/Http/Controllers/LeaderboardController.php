<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    /**
     * Display the public leaderboard.
     */
    public function index(Request $request)
    {
        $activeSession = CompetitionSession::where('is_active', true)->first();

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
            ]);
        }

        $categories = Category::where('session_id', $activeSession->id)->get();

        // Retrieve projects with their average Round 1, Round 2 scores and count of judges
        $projects = Project::where('session_id', $activeSession->id)
            ->where('status', Project::STATUS_APPROVED)
            ->with(['user', 'category'])
            ->get();

        // Query average scores for Round 1
        $r1Averages = DB::table('scores')
            ->where('session_id', $activeSession->id)
            ->where('round_no', 1)
            ->select('project_id', DB::raw('AVG(total) as avg_r1'), DB::raw('COUNT(id) as judges_r1'))
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        // Query average scores for Round 2
        $r2Averages = DB::table('scores')
            ->where('session_id', $activeSession->id)
            ->where('round_no', 2)
            ->select('project_id', DB::raw('AVG(total) as avg_r2'), DB::raw('COUNT(id) as judges_r2'))
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        $scoreDetails = DB::table('scores')
            ->join('users', 'users.id', '=', 'scores.judge_id')
            ->where('scores.session_id', $activeSession->id)
            ->select('scores.project_id', 'scores.round_no', 'scores.total', 'scores.best_presenter', 'users.name as judge_name')
            ->orderBy('scores.round_no')
            ->get()
            ->groupBy('project_id');

        $isAuthenticatedLeaderboard = $request->routeIs('dashboard.leaderboard');

        $leaderboardData = $projects->map(function ($project) use ($r1Averages, $r2Averages, $scoreDetails, $isAuthenticatedLeaderboard) {
            $r1 = $r1Averages->get($project->id);
            $r2 = $r2Averages->get($project->id);

            $avgR1 = $r1 ? round($r1->avg_r1, 2) : 0.0;
            $avgR2 = $r2 ? round($r2->avg_r2, 2) : 0.0;

            // Final score priority: Round 2 if evaluated, else Round 1
            $finalScore = $avgR2 > 0 ? $avgR2 : $avgR1;

            $judges = $isAuthenticatedLeaderboard ? $scoreDetails->get($project->id, collect())->map(fn ($score) => [
                'name' => $score->judge_name,
                'round' => (int) $score->round_no,
                'score' => round((float) $score->total, 2),
                'comments' => $score->comments,
                'submitted_at' => $score->created_at,
            ])->values()->all() : [];

            $presenterVotes = $isAuthenticatedLeaderboard ? $scoreDetails->get($project->id, collect())
                ->filter(fn ($score) => filled($score->best_presenter))
                ->groupBy('best_presenter')
                ->map(fn ($votes, $name) => ['name' => $name, 'votes' => $votes->count()])
                ->sortByDesc('votes')->values()->first() : null;

            return [
                'id' => $project->id,
                'title' => $project->title,
                'pcode' => $project->pcode,
                'category_id' => $project->category_id,
                'category_name' => $project->category?->name ?? 'N/A',
                'username' => $project->user->name,
                'award_level' => $project->award_level,
                'avg_r1' => $avgR1,
                'judges_r1' => $r1 ? $r1->judges_r1 : 0,
                'avg_r2' => $avgR2,
                'judges_r2' => $r2 ? $r2->judges_r2 : 0,
                'final_score' => $finalScore,
                'judges' => $judges,
                'best_presenter' => $presenterVotes,
            ];
        });

        // Group by category and sort by final_score descending
        $groupedData = [];
        foreach ($categories as $cat) {
            $catProjects = $leaderboardData->filter(function ($item) use ($cat) {
                return $item['category_id'] === $cat->id;
            })->sortByDesc('final_score')->values()->all();

            $groupedData[$cat->id] = $catProjects;
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
        ]);
    }
}
