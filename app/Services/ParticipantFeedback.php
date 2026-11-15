<?php

namespace App\Services;

use App\Models\Project;
use App\Models\RubricItem;
use App\Models\Score;

/**
 * Judges' comments and per-criterion scores for a participant, released only
 * after results are finalized. Judges are anonymous ("Judge 1", "Judge 2" per
 * round); best presenter nominations are not included.
 */
class ParticipantFeedback
{
    /**
     * @return array<int, array{
     *     round: int,
     *     average: float,
     *     evaluations: list<array{
     *         label: string,
     *         total: float,
     *         comments: string|null,
     *         criteria: list<array{name: string, section: string|null, score: float, max: int}>
     *     }>
     * }>
     */
    public function forProject(Project $project): array
    {
        $scores = Score::where('project_id', $project->id)
            ->where('session_id', $project->session_id)
            ->orderBy('round_no')
            ->orderBy('id')
            ->get();

        if ($scores->isEmpty()) {
            return [];
        }

        $itemIds = $scores
            ->flatMap(fn (Score $score) => array_keys($score->score_details ?? []))
            ->unique()
            ->values();
        $items = RubricItem::whereIn('id', $itemIds)->orderBy('id')->get()->keyBy('id');

        return $scores
            ->groupBy('round_no')
            ->map(function ($roundScores, $round) use ($items) {
                $judgeNumber = 0;

                return [
                    'round' => (int) $round,
                    'average' => round((float) $roundScores->avg('total'), 2),
                    'evaluations' => $roundScores->map(function (Score $score) use ($items, &$judgeNumber) {
                        $details = $score->score_details ?? [];

                        // Keep the rubric's order rather than the order the scores were saved in.
                        $criteria = $items
                            ->filter(fn (RubricItem $item) => array_key_exists($item->id, $details) || array_key_exists((string) $item->id, $details))
                            ->map(fn (RubricItem $item) => [
                                'name' => $item->criteria_name,
                                'section' => $item->section,
                                'score' => (float) ($details[$item->id] ?? $details[(string) $item->id]),
                                'max' => (int) $item->max_points,
                            ])
                            ->values()
                            ->all();

                        return [
                            'label' => 'Judge '.(++$judgeNumber),
                            'total' => round($score->total, 2),
                            'comments' => filled($score->comments) ? $score->comments : null,
                            'criteria' => $criteria,
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
