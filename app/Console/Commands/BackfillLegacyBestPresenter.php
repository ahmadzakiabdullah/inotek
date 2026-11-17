<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The legacy importer copied every submitted score but left best_presenter empty,
 * so Best Presenter nominations made in the old system never reached the
 * leaderboard. This copies only that field, and only into imported scores that
 * still have no nomination. Nothing else is read from or written to.
 */
class BackfillLegacyBestPresenter extends Command
{
    protected $signature = 'inotek:backfill-legacy-presenters {--commit : Write the nominations (default is a preview)}';

    protected $description = 'Copy Best Presenter nominations from the legacy score table into imported scores that have none.';

    public function handle(): int
    {
        foreach (['score', 'sessions', 'scores', 'projects', 'users', 'competition_sessions'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Required table '{$table}' is missing.");

                return self::FAILURE;
            }
        }

        if (! Schema::hasColumn('score', 'best_presenter')) {
            $this->error("The legacy 'score' table has no best_presenter column, so there is nothing to copy.");

            return self::FAILURE;
        }

        $legacyScores = DB::table('score')
            ->where('status', 1)
            ->whereNotNull('best_presenter')
            ->where('best_presenter', '<>', '')
            ->get(['session_id', 'pcode', 'juri', 'round_no', 'best_presenter']);

        // Same matching as inotek:import-legacy-competition: session by name,
        // project by session and code, judge by username.
        // Alias both ids: they share the column name, so pluck would otherwise return the same one twice.
        $sessionIds = DB::table('sessions')
            ->join('competition_sessions', 'competition_sessions.name', '=', 'sessions.name')
            ->select('sessions.id as legacy_id', 'competition_sessions.id as target_id')
            ->pluck('target_id', 'legacy_id');
        $judgeIds = DB::table('users')->whereNotNull('username')->pluck('id', 'username');

        $values = $legacyScores->countBy(fn ($row) => trim((string) $row->best_presenter));
        $updates = [];
        $unmatched = 0;
        $alreadySet = 0;

        foreach ($legacyScores as $legacy) {
            $sessionId = $sessionIds[$legacy->session_id] ?? null;
            $judgeId = $judgeIds[(string) $legacy->juri] ?? null;
            $projectId = $sessionId === null ? null : DB::table('projects')
                ->where('session_id', $sessionId)
                ->where('pcode', $legacy->pcode)
                ->value('id');

            $score = ($projectId === null || $judgeId === null) ? null : DB::table('scores')
                ->where('project_id', $projectId)
                ->where('judge_id', $judgeId)
                ->where('session_id', $sessionId)
                ->where('round_no', max(1, (int) $legacy->round_no))
                ->first(['id', 'best_presenter']);

            if ($score === null) {
                $unmatched++;
            } elseif (filled($score->best_presenter)) {
                $alreadySet++;
            } else {
                $updates[$score->id] = trim((string) $legacy->best_presenter);
            }
        }

        $this->table(['Item', 'Count'], [
            ['Legacy scores with a nomination', $legacyScores->count()],
            ['Will be copied', count($updates)],
            ['Already have a nomination (left as is)', $alreadySet],
            ['No matching imported score', $unmatched],
        ]);

        $this->line('Legacy best_presenter values (most common first):');
        $this->table(['Value', 'Count'], $values->sortDesc()->take(15)->map(fn ($count, $value) => [$value, $count])->values()->all());

        if (! $this->option('commit')) {
            $this->info('Preview only: nothing was written. Run again with --commit to copy the nominations.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($updates): void {
            foreach ($updates as $scoreId => $nominee) {
                DB::table('scores')->where('id', $scoreId)->update(['best_presenter' => $nominee]);
            }
        });

        $this->info('Copied '.count($updates).' Best Presenter nominations.');

        return self::SUCCESS;
    }
}
