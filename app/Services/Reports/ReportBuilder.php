<?php

namespace App\Services\Reports;

use App\Models\CompetitionSession;
use App\Models\JudgeAssignment;
use App\Models\Project;
use App\Models\RubricItem;
use App\Models\Score;
use App\Services\CompetitionResults;
use InvalidArgumentException;

class ReportBuilder
{
    /**
     * Report types, with the label and description shown on the reports page.
     */
    public const TYPES = [
        'results' => [
            'title' => 'Official Results by Category',
            'description' => 'Rank, Round 1 and Round 2 averages, final score and award for every approved project.',
        ],
        'participants' => [
            'title' => 'Participants & Supervisors',
            'description' => 'Every registered project with its status, participant, supervisor contacts and team members.',
        ],
        'judge-scores' => [
            'title' => 'Judge Scores (Detailed)',
            'description' => 'Every submitted evaluation: judge, round, total score, best presenter nomination and comments.',
        ],
        'judge-criteria' => [
            'title' => 'Judge Scores by Rubric Criterion',
            'description' => 'One row per evaluation with the points given for each rubric criterion (A1, A2, ...) and the total score.',
        ],
        'judging-status' => [
            'title' => 'Judging Progress',
            'description' => 'Every judge assignment and whether its evaluation has been submitted.',
        ],
    ];

    public function __construct(private readonly CompetitionResults $results) {}

    public function build(string $type, CompetitionSession $session): Report
    {
        return match ($type) {
            'results' => $this->results($session),
            'participants' => $this->participants($session),
            'judge-scores' => $this->judgeScores($session),
            'judge-criteria' => $this->judgeCriteria($session),
            'judging-status' => $this->judgingStatus($session),
            default => throw new InvalidArgumentException("Unknown report type [{$type}]."),
        };
    }

    private function results(CompetitionSession $session): Report
    {
        $rows = collect($this->results->forSession($session))->map(fn (array $row) => [
            $row['project']->category->name ?? '-',
            $row['rank'] ?? '-',
            $row['project']->pcode ?? '-',
            $row['project']->title,
            $row['project']->user->name ?? '-',
            $row['project']->supervisor_name ?? '-',
            $this->score($row['avg_r1'], $row['judges_r1']),
            $this->score($row['avg_r2'], $row['judges_r2']),
            $row['final_score'] ?? '-',
            $row['project']->award_level ?? '-',
        ])->all();

        return new Report(
            title: self::TYPES['results']['title'],
            sessionName: $session->name,
            columns: ['Category', 'Rank', 'Code', 'Project Title', 'Participant', 'Supervisor', 'R1 Avg (judges)', 'R2 Avg (judges)', 'Final Score', 'Award'],
            rows: $rows,
            note: $session->r2_locked ? null : 'DRAFT: Round 2 has not been finalized, so these results and awards may still change.',
        );
    }

    private function participants(CompetitionSession $session): Report
    {
        $rows = Project::where('session_id', $session->id)
            ->with(['category', 'user', 'teamMembers'])
            ->orderBy('category_id')
            ->orderBy('pcode')
            ->get()
            ->map(fn (Project $project) => [
                $project->pcode ?? '-',
                $project->title,
                $project->category->name ?? '-',
                $project->status_label,
                $project->institution_type === 'utem' ? 'UTeM' : 'Other IPT',
                $project->user->name ?? '-',
                $project->user->email ?? '-',
                $project->supervisor_name ?? '-',
                $project->supervisor_email ?? '-',
                $project->supervisor_phone ?? '-',
                $project->teamMembers->pluck('name')->filter()->implode('; ') ?: '-',
                $project->created_at?->format('d/m/Y'),
            ])
            ->all();

        return new Report(
            title: self::TYPES['participants']['title'],
            sessionName: $session->name,
            columns: ['Code', 'Project Title', 'Category', 'Status', 'Institution', 'Participant', 'Participant Email', 'Supervisor', 'Supervisor Email', 'Supervisor Phone', 'Team Members', 'Registered'],
            rows: $rows,
        );
    }

    private function judgeScores(CompetitionSession $session): Report
    {
        $rows = Score::where('session_id', $session->id)
            ->whereHas('project')
            ->with(['project.category', 'judge'])
            ->get()
            ->sortBy(fn (Score $score) => [$score->round_no, $score->project->category->code ?? '', $score->project->pcode ?? '', $score->judge->name ?? ''])
            ->map(fn (Score $score) => [
                $score->round_no,
                $score->project->pcode ?? '-',
                $score->project->title,
                $score->project->category->name ?? '-',
                $score->judge->name ?? '(deleted judge)',
                round($score->total, 2),
                $score->best_presenter ?: '-',
                $score->comments ?: '-',
                $score->updated_at?->format('d/m/Y H:i'),
            ])
            ->values()
            ->all();

        return new Report(
            title: self::TYPES['judge-scores']['title'],
            sessionName: $session->name,
            columns: ['Round', 'Code', 'Project Title', 'Category', 'Judge', 'Total Score', 'Best Presenter', 'Comments', 'Submitted'],
            rows: $rows,
        );
    }

    /**
     * One row per evaluation with one column per rubric criterion (raw points
     * the judge gave), so admins can audit how each judge arrived at the
     * total. Categories may use different rubrics, so the columns are the
     * union of every criterion used; a criterion outside a project's rubric
     * is left blank.
     */
    private function judgeCriteria(CompetitionSession $session): Report
    {
        $scores = Score::where('session_id', $session->id)
            ->whereHas('project')
            ->with(['project.category', 'judge'])
            ->get()
            ->sortBy(fn (Score $score) => [$score->round_no, $score->project->category->code ?? '', $score->project->pcode ?? '', $score->judge->name ?? ''])
            ->values();

        $itemIds = $scores
            ->flatMap(fn (Score $score) => array_keys($score->score_details ?? []))
            ->unique()
            ->values();
        $items = RubricItem::whereIn('id', $itemIds)->orderBy('rubric_id')->orderBy('id')->get();

        // Criteria sharing a label (e.g. "A1" in two rubrics) share a column.
        $columns = [];
        foreach ($items as $item) {
            $label = $item->code ?: $item->criteria_name;
            $columns[$label][] = $item->id;
        }

        $rows = [];
        foreach ($scores as $score) {
            $details = $score->score_details ?? [];
            $points = [];
            foreach ($columns as $ids) {
                $value = '';
                foreach ($ids as $id) {
                    if (array_key_exists($id, $details) || array_key_exists((string) $id, $details)) {
                        $value = round((float) ($details[$id] ?? $details[(string) $id]), 2);
                        break;
                    }
                }
                $points[] = $value;
            }

            $rows[] = [
                $score->round_no,
                $score->project->pcode ?? '-',
                $score->project->title,
                $score->project->category->name ?? '-',
                $score->judge->name ?? '(deleted judge)',
                ...$points,
                round($score->total, 2),
            ];
        }

        return new Report(
            title: self::TYPES['judge-criteria']['title'],
            sessionName: $session->name,
            columns: ['Round', 'Code', 'Project Title', 'Category', 'Judge', ...array_map('strval', array_keys($columns)), 'Total'],
            rows: $rows,
        );
    }

    private function judgingStatus(CompetitionSession $session): Report
    {
        $scores = Score::where('session_id', $session->id)
            ->get()
            ->keyBy(fn (Score $score) => "{$score->project_id}-{$score->judge_id}-{$score->round_no}");

        $rows = JudgeAssignment::where('session_id', $session->id)
            ->whereHas('project')
            ->whereHas('judge')
            ->with(['project.category', 'judge'])
            ->get()
            ->sortBy(fn (JudgeAssignment $assignment) => [$assignment->round_no, $assignment->judge->name, $assignment->project->pcode ?? ''])
            ->map(function (JudgeAssignment $assignment) use ($scores) {
                $score = $scores->get("{$assignment->project_id}-{$assignment->judge_id}-{$assignment->round_no}");

                return [
                    $assignment->round_no,
                    $assignment->judge->name,
                    $assignment->project->pcode ?? '-',
                    $assignment->project->title,
                    $assignment->project->category->name ?? '-',
                    $score ? 'Submitted' : 'Pending',
                    $score ? round($score->total, 2) : '-',
                ];
            })
            ->values()
            ->all();

        return new Report(
            title: self::TYPES['judging-status']['title'],
            sessionName: $session->name,
            columns: ['Round', 'Judge', 'Code', 'Project Title', 'Category', 'Status', 'Score'],
            rows: $rows,
        );
    }

    private function score(?float $average, int $judges): string
    {
        return $average === null ? '-' : "{$average} ({$judges})";
    }
}
