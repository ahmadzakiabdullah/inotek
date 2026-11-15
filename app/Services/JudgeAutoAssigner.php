<?php

namespace App\Services;

use App\Models\CompetitionSession;
use App\Models\JudgeAssignment;
use App\Models\Project;
use App\Models\Score;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Assigns judges in bulk so every project in a round has the requested number
 * of judges, spreading the work evenly. It applies the same rules as manual
 * assignment: no conflict of interest, no Round 2 judge who scored the project
 * in Round 1, Round 2 only for shortlisted projects, and no duplicates.
 */
class JudgeAutoAssigner
{
    public const SETTING_ENABLED = 'auto_assign_enabled';

    public const SETTING_JUDGES_PER_PROJECT = 'auto_assign_judges_per_project';

    public const DEFAULT_JUDGES_PER_PROJECT = 3;

    public const MAX_JUDGES_PER_PROJECT = 10;

    public function __construct(private readonly ScoreCalculator $calculator) {}

    public static function isEnabled(): bool
    {
        return filter_var(Setting::get(self::SETTING_ENABLED, '0'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function judgesPerProject(): int
    {
        $value = (int) Setting::get(self::SETTING_JUDGES_PER_PROJECT, (string) self::DEFAULT_JUDGES_PER_PROJECT);

        return max(1, min(self::MAX_JUDGES_PER_PROJECT, $value));
    }

    /**
     * @return array{
     *     created: list<array{project: Project, judge: User}>,
     *     projects_considered: int,
     *     unfilled: list<array{project: Project, missing: int}>
     * }
     */
    public function assign(CompetitionSession $session, int $roundNo, int $judgesPerProject): array
    {
        return DB::transaction(function () use ($session, $roundNo, $judgesPerProject) {
            // Serialize concurrent runs for this session so they cannot double-assign.
            CompetitionSession::whereKey($session->id)->lockForUpdate()->first();

            $projects = $this->targetProjects($session, $roundNo);
            $judges = User::withRole('judge')->orderBy('id')->get();

            $assigned = JudgeAssignment::where('session_id', $session->id)
                ->where('round_no', $roundNo)
                ->get(['project_id', 'judge_id']);

            /** @var array<int, list<int>> $judgesByProject */
            $judgesByProject = [];
            foreach ($assigned as $assignment) {
                $judgesByProject[$assignment->project_id][] = $assignment->judge_id;
            }

            // Current workload in this round, counted only for projects that still exist.
            $liveProjectIds = $projects->pluck('id')->flip();
            $load = $judges->mapWithKeys(fn (User $judge) => [$judge->id => 0])->all();
            foreach ($assigned as $assignment) {
                if (isset($load[$assignment->judge_id]) && $liveProjectIds->has($assignment->project_id)) {
                    $load[$assignment->judge_id]++;
                }
            }

            // In Round 2, a judge who scored the project in Round 1 is not eligible.
            $roundOneScorers = $roundNo === 2
                ? Score::where('session_id', $session->id)->where('round_no', 1)->get(['project_id', 'judge_id'])
                    ->groupBy('project_id')->map(fn (Collection $scores) => $scores->pluck('judge_id')->all())
                : collect();

            $created = [];
            $unfilled = [];

            // Fill the projects with the fewest judges first.
            $ordered = $projects->sortBy(fn (Project $project) => [count($judgesByProject[$project->id] ?? []), $project->pcode ?? '', $project->id]);

            foreach ($ordered as $project) {
                $current = $judgesByProject[$project->id] ?? [];
                $missing = $judgesPerProject - count($current);

                if ($missing <= 0) {
                    continue;
                }

                $candidates = $judges
                    ->reject(fn (User $judge) => in_array($judge->id, $current, true))
                    ->reject(fn (User $judge) => in_array($judge->id, $roundOneScorers->get($project->id, []), true))
                    ->reject(fn (User $judge) => $this->calculator->hasConflictOfInterest($project, $judge))
                    ->sortBy(fn (User $judge) => [$load[$judge->id], $judge->id])
                    ->take($missing);

                foreach ($candidates as $judge) {
                    JudgeAssignment::create([
                        'project_id' => $project->id,
                        'judge_id' => $judge->id,
                        'session_id' => $session->id,
                        'round_no' => $roundNo,
                    ]);
                    $load[$judge->id]++;
                    $judgesByProject[$project->id][] = $judge->id;
                    $created[] = ['project' => $project, 'judge' => $judge];
                }

                if ($candidates->count() < $missing) {
                    $unfilled[] = ['project' => $project, 'missing' => $missing - $candidates->count()];
                }
            }

            return [
                'created' => $created,
                'projects_considered' => $projects->count(),
                'unfilled' => $unfilled,
            ];
        });
    }

    /**
     * @return Collection<int, Project>
     */
    private function targetProjects(CompetitionSession $session, int $roundNo): Collection
    {
        $query = Project::where('session_id', $session->id)
            ->where('status', Project::STATUS_APPROVED);

        if ($roundNo === 2) {
            $shortlistedIds = $this->calculator
                ->getRound1Shortlist($session->id, $session->round2_shortlist_limit ?: 3)
                ->flatten()
                ->pluck('id')
                ->all();

            $query->whereIn('id', $shortlistedIds);
        }

        return $query->get();
    }
}
