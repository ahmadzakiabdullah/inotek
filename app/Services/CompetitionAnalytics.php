<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\Project;
use App\Models\Score;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompetitionAnalytics
{
    /**
     * A judge needs at least this many evaluations shared with other judges
     * before their average deviation is meaningful.
     */
    public const MIN_SHARED_EVALUATIONS = 3;

    public function __construct(private readonly CompetitionResults $results) {}

    /**
     * Participation and judging totals for every session, oldest first.
     *
     * @return array<int, array{session_id: int, name: string, registered: int, approved: int, participants: int, judges: int, evaluations: int}>
     */
    public function trend(): array
    {
        $projects = Project::select('session_id', 'status', 'user_id')->get()->groupBy('session_id');
        $judges = DB::table('judge_assignments')
            ->select('session_id', DB::raw('COUNT(DISTINCT judge_id) as judges'))
            ->groupBy('session_id')
            ->pluck('judges', 'session_id');
        $evaluations = DB::table('scores')
            ->select('session_id', DB::raw('COUNT(*) as evaluations'))
            ->groupBy('session_id')
            ->pluck('evaluations', 'session_id');

        return CompetitionSession::orderBy('id')->get(['id', 'name'])
            ->map(function (CompetitionSession $session) use ($projects, $judges, $evaluations) {
                $sessionProjects = $projects->get($session->id, collect());

                return [
                    'session_id' => $session->id,
                    'name' => $session->name,
                    'registered' => $sessionProjects->count(),
                    'approved' => $sessionProjects->where('status', Project::STATUS_APPROVED)->count(),
                    'participants' => $sessionProjects->pluck('user_id')->unique()->count(),
                    'judges' => (int) ($judges[$session->id] ?? 0),
                    'evaluations' => (int) ($evaluations[$session->id] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Breakdown of one session.
     *
     * @return array<string, mixed>
     */
    public function forSession(CompetitionSession $session): array
    {
        $projects = Project::where('session_id', $session->id)->get(['id', 'category_id', 'status', 'institution_type']);
        $results = $this->results->forSession($session);
        $scores = Score::where('session_id', $session->id)
            ->whereIn('project_id', $projects->pluck('id'))
            ->with('judge:id,name')
            ->get(['id', 'project_id', 'judge_id', 'round_no', 'total']);

        return [
            'summary' => [
                'registered' => $projects->count(),
                'approved' => $projects->where('status', Project::STATUS_APPROVED)->count(),
                'pending' => $projects->where('status', Project::STATUS_SUBMITTED)->count(),
                'judges' => $scores->pluck('judge_id')->unique()->count(),
                'evaluations' => $scores->count(),
                'average_score' => $scores->isEmpty() ? null : round((float) $scores->avg('total'), 2),
            ],
            'byCategory' => $this->byCategory($projects, $results),
            'byInstitution' => $this->byInstitution($projects),
            'scoreDistribution' => $this->scoreDistribution($scores),
            'judgeConsistency' => $this->judgeConsistency($scores),
        ];
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @param  array<int, array{project: Project, avg_r1: float|null, judges_r1: int, avg_r2: float|null, judges_r2: int, final_score: float|null, rank: int|null}>  $results
     * @return array<int, array{category: string, registered: int, approved: int, average_score: float|null}>
     */
    private function byCategory(Collection $projects, array $results): array
    {
        $categories = Category::whereIn('id', $projects->pluck('category_id')->unique())->orderBy('code')->get();
        $scoresByCategory = collect($results)
            ->filter(fn (array $row) => $row['final_score'] !== null)
            ->groupBy(fn (array $row) => $row['project']->category_id);

        return $categories->map(function (Category $category) use ($projects, $scoresByCategory) {
            $categoryProjects = $projects->where('category_id', $category->id);
            $finalScores = $scoresByCategory->get($category->id, collect())->pluck('final_score');

            return [
                'category' => $category->name,
                'registered' => $categoryProjects->count(),
                'approved' => $categoryProjects->where('status', Project::STATUS_APPROVED)->count(),
                'average_score' => $finalScores->isEmpty() ? null : round((float) $finalScores->avg(), 2),
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return array<int, array{institution: string, registered: int, approved: int}>
     */
    private function byInstitution(Collection $projects): array
    {
        return collect(['utem' => 'UTeM', 'ipt' => 'Other IPT'])
            ->map(fn (string $label, string $type) => [
                'institution' => $label,
                'registered' => $projects->where('institution_type', $type)->count(),
                'approved' => $projects->where('institution_type', $type)->where('status', Project::STATUS_APPROVED)->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * Number of evaluations in each 10-point band, per round.
     *
     * @param  Collection<int, Score>  $scores
     * @return array<int, array{band: string, round1: int, round2: int}>
     */
    private function scoreDistribution(Collection $scores): array
    {
        $round1 = array_fill(0, 10, 0);
        $round2 = array_fill(0, 10, 0);

        foreach ($scores as $score) {
            // Band 9 is 90–100, so a perfect 100 lands in the top band.
            $band = min(9, max(0, (int) floor($score->total / 10)));
            if ($score->round_no === 2) {
                $round2[$band]++;
            } else {
                $round1[$band]++;
            }
        }

        $bands = [];
        for ($band = 0; $band < 10; $band++) {
            $low = $band * 10;
            $bands[] = [
                'band' => $band === 9 ? '90–100' : $low.'–'.($low + 9),
                'round1' => $round1[$band],
                'round2' => $round2[$band],
            ];
        }

        return $bands;
    }

    /**
     * How each judge's scores compare with the other judges of the same project
     * and round. A positive deviation means the judge scores higher than their
     * peers on average (more lenient); negative means stricter.
     *
     * @param  Collection<int, Score>  $scores
     * @return array<int, array{judge: string, evaluations: int, average_given: float, shared_evaluations: int, average_deviation: float|null}>
     */
    private function judgeConsistency(Collection $scores): array
    {
        $groups = $scores->groupBy(fn (Score $score) => "{$score->project_id}-{$score->round_no}");

        $deviations = [];
        foreach ($groups as $group) {
            if ($group->count() < 2) {
                continue;
            }

            foreach ($group as $score) {
                // Compare with the mean of the *other* judges, so a judge's own score does not pull the baseline.
                $othersMean = ($group->sum('total') - $score->total) / ($group->count() - 1);
                $deviations[$score->judge_id][] = $score->total - $othersMean;
            }
        }

        return $scores
            ->groupBy('judge_id')
            ->map(function (Collection $judgeScores, int $judgeId) use ($deviations) {
                $shared = $deviations[$judgeId] ?? [];

                return [
                    'judge' => $judgeScores->first()->judge->name ?? '(deleted judge)',
                    'evaluations' => $judgeScores->count(),
                    'average_given' => round((float) $judgeScores->avg('total'), 2),
                    'shared_evaluations' => count($shared),
                    'average_deviation' => count($shared) >= self::MIN_SHARED_EVALUATIONS
                        ? round(array_sum($shared) / count($shared), 2)
                        : null,
                ];
            })
            ->sortByDesc(fn (array $row) => abs($row['average_deviation'] ?? 0))
            ->values()
            ->all();
    }
}
