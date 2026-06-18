<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Score;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class ScoreCalculator
{
    /**
     * Calculate the total score out of 100 based on rubric items and raw points.
     *
     * @param Project $project
     * @param array $scoreDetails Key-value mapping of rubric_item_id => points (0-5)
     * @return float
     */
    public function calculateTotal(Project $project, array $scoreDetails): float
    {
        $category = $project->category;
        if (!$category) {
            return 0.0;
        }

        // Find mapped rubric
        $rubric = $category->rubrics->first();
        if (!$rubric) {
            return 0.0;
        }

        $rubricItems = $rubric->items;
        if ($rubricItems->isEmpty()) {
            return 0.0;
        }

        $totalScore = 0.0;

        foreach ($rubricItems as $item) {
            $itemId = $item->id;
            $points = isset($scoreDetails[$itemId]) ? (float) $scoreDetails[$itemId] : 0.0;
            $maxPoints = (float) $item->max_points;
            $weight = (float) $item->weight;

            if ($maxPoints > 0) {
                // Score contribution = (points / max_points) * weight * 100
                $contribution = ($points / $maxPoints) * $weight * 100.0;
                $totalScore += $contribution;
            }
        }

        return round($totalScore, 2);
    }

    /**
     * Get the Round 1 qualified projects for Round 2.
     * Selects Top N projects per category, ordered by average score.
     *
     * @param int $sessionId
     * @param int $limit Top N projects to select per category (e.g. 3 or 5)
     * @return \Illuminate\Support\Collection
     */
    public function getRound1Shortlist(int $sessionId, int $limit = 3)
    {
        // Get categories for the active session
        $categories = Category::where('session_id', $sessionId)->get();
        $shortlist = collect();

        foreach ($categories as $category) {
            // Get projects in this category with their average Round 1 score
            $projects = Project::where('projects.session_id', $sessionId)
                ->where('projects.category_id', $category->id)
                ->where('projects.status', Project::STATUS_APPROVED)
                ->join('scores', 'projects.id', '=', 'scores.project_id')
                ->where('scores.round_no', 1)
                ->select('projects.*', DB::raw('AVG(scores.total) as avg_score'), DB::raw('COUNT(scores.id) as judges_count'))
                ->groupBy('projects.id')
                ->orderBy('avg_score', 'desc')
                ->limit($limit)
                ->get();

            $shortlist->put($category->id, $projects);
        }

        return $shortlist;
    }

    /**
     * Validate if a judge is allowed to be assigned to evaluate a project in Round 2.
     * Returns true if they DID NOT evaluate the same project in Round 1.
     *
     * @param int $projectId
     * @param int $judgeId
     * @param int $sessionId
     * @return bool
     */
    public function isJudgeEligibleForRound2(int $projectId, int $judgeId, int $sessionId): bool
    {
        // Check if the judge scored the project in Round 1
        $existsInRound1 = Score::where('project_id', $projectId)
            ->where('judge_id', $judgeId)
            ->where('session_id', $sessionId)
            ->where('round_no', 1)
            ->exists();

        return !$existsInRound1;
    }
}
