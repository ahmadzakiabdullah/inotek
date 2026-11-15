<?php

namespace App\Services;

use App\Models\CompetitionSession;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Per-project results for one session, ranked within each category the same
 * way as the leaderboard: projects with a Round 2 score rank above projects
 * judged only in Round 1, then by final score.
 */
class CompetitionResults
{
    /**
     * @return array<int, array{
     *     project: Project,
     *     avg_r1: float|null,
     *     judges_r1: int,
     *     avg_r2: float|null,
     *     judges_r2: int,
     *     final_score: float|null,
     *     rank: int|null
     * }>
     */
    public function forSession(CompetitionSession $session): array
    {
        $projects = Project::where('session_id', $session->id)
            ->where('status', Project::STATUS_APPROVED)
            ->with(['category', 'user'])
            ->get();

        $averages = DB::table('scores')
            ->where('session_id', $session->id)
            ->whereIn('project_id', $projects->pluck('id'))
            ->select('project_id', 'round_no', DB::raw('AVG(total) as avg_total'), DB::raw('COUNT(id) as judges'))
            ->groupBy('project_id', 'round_no')
            ->get()
            ->groupBy('project_id');

        $rows = [];
        foreach ($projects as $project) {
            $byRound = $averages->get($project->id, collect())->keyBy('round_no');
            $r1 = $byRound->get(1);
            $r2 = $byRound->get(2);

            $avgR1 = $r1 ? round((float) $r1->avg_total, 2) : null;
            $avgR2 = $r2 ? round((float) $r2->avg_total, 2) : null;

            $rows[] = [
                'project' => $project,
                'avg_r1' => $avgR1,
                'judges_r1' => $r1 ? (int) $r1->judges : 0,
                'avg_r2' => $avgR2,
                'judges_r2' => $r2 ? (int) $r2->judges : 0,
                // Presence of a Round 2 score, not its value, decides which round counts.
                'final_score' => $avgR2 ?? $avgR1,
            ];
        }

        // Category code, then Round 2 finalists first, then score (highest first), then project code.
        usort($rows, fn (array $a, array $b) => [
            $a['project']->category->code ?? '', $b['judges_r2'] > 0, $b['final_score'] ?? -1, $a['project']->pcode ?? '',
        ] <=> [
            $b['project']->category->code ?? '', $a['judges_r2'] > 0, $a['final_score'] ?? -1, $b['project']->pcode ?? '',
        ]);

        // Rank within each category. Unscored projects are listed but not ranked.
        $rankByCategory = [];
        $ranked = [];
        foreach ($rows as $row) {
            $rank = null;
            if ($row['final_score'] !== null) {
                $categoryId = $row['project']->category_id;
                $rank = $rankByCategory[$categoryId] = ($rankByCategory[$categoryId] ?? 0) + 1;
            }

            $ranked[] = [...$row, 'rank' => $rank];
        }

        return $ranked;
    }
}
