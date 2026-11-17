<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\JudgeAssignment;
use App\Models\Project;
use App\Models\Round2Wildcard;
use App\Models\Rubric;
use App\Models\Score;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScoreCalculator
{
    /**
     * Calculate the total score out of 100 based on rubric items and raw points.
     *
     * @param  array<int|string, int|float>  $scoreDetails  Key-value mapping of rubric_item_id => points within that item's maximum
     */
    public function calculateTotal(Project $project, array $scoreDetails, ?Rubric $rubric = null): float
    {
        if (! $rubric) {
            $category = $project->category;
            if (! $category) {
                return 0.0;
            }

            $rubric = $category->rubricsForSession($project->session_id)->orderByDesc('rubrics.id')->first();
        }
        if (! $rubric) {
            return 0.0;
        }

        $rubricItems = $rubric->items;
        if ($rubricItems->isEmpty()) {
            return 0.0;
        }

        $totalWeight = $rubricItems->sum(fn ($item) => (float) $item->weight);
        if ($totalWeight <= 0) {
            return 0.0;
        }

        $totalScore = 0.0;

        foreach ($rubricItems as $item) {
            $itemId = $item->id;
            // Older imported evaluations may not have a score for every item
            // in the current rubric. Treat missing criteria as zero instead
            // of raising an undefined array key warning.
            $points = (float) ($scoreDetails[$itemId] ?? 0);
            $maxPoints = (float) $item->max_points;
            $weight = (float) $item->weight;

            if ($maxPoints > 0) {
                // Normalize configured weights so every rubric has a maximum of 100.
                $contribution = ($points / $maxPoints) * ($weight / $totalWeight) * 100.0;
                $totalScore += $contribution;
            }
        }

        return round($totalScore, 2);
    }

    /**
     * Get the Round 1 qualified projects for Round 2.
     * Selects Top N projects per category, ordered by average score.
     *
     * @param  int  $limit  Top N projects to select per category (e.g. 3 or 5)
     * @return Collection<int, Collection<int, Project>>
     */
    public function getRound1Shortlist(int $sessionId, int $limit = 3)
    {
        // Categories are shared across sessions; only query those with approved
        // projects in this session, but keep every key so the response shape is stable.
        $categories = Category::orderBy('code')->get();
        $activeCategoryIds = Project::where('session_id', $sessionId)
            ->where('status', Project::STATUS_APPROVED)
            ->distinct()
            ->pluck('category_id')
            ->all();
        $shortlist = collect();

        foreach ($categories as $category) {
            if (! in_array($category->id, $activeCategoryIds)) {
                $shortlist->put($category->id, collect());

                continue;
            }

            // Get projects in this category with their average Round 1 score.
            // Ties are broken by project code so the shortlist is deterministic.
            $projects = Project::where('projects.session_id', $sessionId)
                ->where('projects.category_id', $category->id)
                ->where('projects.status', Project::STATUS_APPROVED)
                ->join('scores', 'projects.id', '=', 'scores.project_id')
                ->where('scores.round_no', 1)
                ->select('projects.*', DB::raw('AVG(scores.total) as avg_score'), DB::raw('COUNT(scores.id) as judges_count'))
                ->groupBy('projects.id')
                ->orderBy('avg_score', 'desc')
                ->orderBy('projects.pcode')
                ->limit($limit)
                ->get();

            $shortlist->put($category->id, $projects);
        }

        return $shortlist;
    }

    /**
     * Ties at the shortlist cut-off: categories where projects just outside the
     * Top N have the same Round 1 average as the last shortlisted project. The
     * shortlist breaks such ties by project code, so admins should review them.
     *
     * @return array<int, array{in: list<array{pcode: string|null, title: string, avg_score: float}>, out: list<array{pcode: string|null, title: string, avg_score: float}>}>
     */
    public function shortlistBoundaryTies(int $sessionId, int $limit): array
    {
        $rows = DB::table('projects')
            ->where('projects.session_id', $sessionId)
            ->where('projects.status', Project::STATUS_APPROVED)
            ->whereNull('projects.deleted_at')
            ->join('scores', 'projects.id', '=', 'scores.project_id')
            ->where('scores.round_no', 1)
            ->select('projects.id', 'projects.pcode', 'projects.title', 'projects.category_id', DB::raw('AVG(scores.total) as avg_score'))
            ->groupBy('projects.id', 'projects.pcode', 'projects.title', 'projects.category_id')
            // Same order as getRound1Shortlist().
            ->orderBy('avg_score', 'desc')
            ->orderBy('projects.pcode')
            ->get()
            ->groupBy('category_id');

        $ties = [];

        foreach ($rows as $categoryId => $projects) {
            if ($projects->count() <= $limit) {
                continue;
            }

            // Averages are compared at the precision shown on screen.
            $cutOff = round((float) $projects[$limit - 1]->avg_score, 2);
            $describe = fn ($project) => [
                'pcode' => $project->pcode,
                'title' => $project->title,
                'avg_score' => round((float) $project->avg_score, 2),
            ];
            $out = $projects->slice($limit)->filter(fn ($project) => round((float) $project->avg_score, 2) === $cutOff);

            if ($out->isNotEmpty()) {
                $ties[(int) $categoryId] = [
                    'in' => array_values($projects->take($limit)->filter(fn ($project) => round((float) $project->avg_score, 2) === $cutOff)->map($describe)->all()),
                    'out' => array_values($out->map($describe)->all()),
                ];
            }
        }

        return $ties;
    }

    /**
     * Every project in Round 2: the automatic Round 1 shortlist plus the
     * wildcards an admin added. Use this, not the shortlist alone, wherever
     * Round 2 membership matters (judge assignments, awards).
     *
     * @return array<int, int>
     */
    public function getRound2ProjectIds(CompetitionSession $session): array
    {
        $shortlisted = $this->getRound1Shortlist($session->id, $session->round2_shortlist_limit ?: 3)
            ->flatten()
            ->pluck('id');

        $wildcards = Round2Wildcard::where('session_id', $session->id)
            ->whereHas('project', fn ($query) => $query->where('status', Project::STATUS_APPROVED))
            ->pluck('project_id');

        return $shortlisted->merge($wildcards)->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Validate if a judge is allowed to be assigned to evaluate a project in Round 2.
     * Returns true if they DID NOT evaluate the same project in Round 1.
     */
    public function isJudgeEligibleForRound2(int $projectId, int $judgeId, int $sessionId): bool
    {
        return ! in_array($judgeId, $this->roundOneJudgeIds($projectId, $sessionId), true);
    }

    /**
     * Judges who were assigned to, or scored, the project in Round 1. Either
     * one rules them out of Round 2 for that project: Round 2 needs a fresh
     * view, and an assignment counts even before its score is submitted.
     *
     * @return array<int, int>
     */
    public function roundOneJudgeIds(int $projectId, int $sessionId): array
    {
        $assigned = JudgeAssignment::where('project_id', $projectId)
            ->where('session_id', $sessionId)
            ->where('round_no', 1)
            ->pluck('judge_id');

        $scored = Score::where('project_id', $projectId)
            ->where('session_id', $sessionId)
            ->where('round_no', 1)
            ->pluck('judge_id');

        return $assigned->merge($scored)->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Whether every Round 1 assignment in the session has a submitted score.
     * Returns false when there are no Round 1 assignments.
     *
     * Assignments of deleted projects or deleted judges are ignored: they can
     * never be scored, and would otherwise block Round 2 indefinitely.
     */
    public function isRoundOneComplete(int $sessionId): bool
    {
        $assignments = DB::table('judge_assignments')
            ->join('projects', 'projects.id', '=', 'judge_assignments.project_id')
            ->join('users', 'users.id', '=', 'judge_assignments.judge_id')
            ->whereNull('projects.deleted_at')
            ->whereNull('users.deleted_at')
            ->where('judge_assignments.session_id', $sessionId)
            ->where('judge_assignments.round_no', 1);

        if (! (clone $assignments)->exists()) {
            return false;
        }

        return ! $assignments->whereNotExists(fn ($query) => $query->from('scores')
            ->whereColumn('scores.project_id', 'judge_assignments.project_id')
            ->whereColumn('scores.judge_id', 'judge_assignments.judge_id')
            ->whereColumn('scores.session_id', 'judge_assignments.session_id')
            ->where('scores.round_no', 1)
        )->exists();
    }

    /**
     * Round 1 scores are frozen once Round 2 judging has started (any Round 2
     * assignment exists), so the shortlist cannot shift underneath Round 2.
     */
    public function isRoundOneLocked(int $sessionId): bool
    {
        return DB::table('judge_assignments')
            ->where('session_id', $sessionId)
            ->where('round_no', 2)
            ->exists();
    }

    /**
     * A judge has a conflict of interest when they own or supervise the project.
     */
    public function hasConflictOfInterest(Project $project, User $judge): bool
    {
        return $project->user_id === $judge->id
            || ($project->supervisor_email !== null
                && strcasecmp(trim($project->supervisor_email), trim($judge->email)) === 0);
    }
}
