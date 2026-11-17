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
    protected $signature = 'inotek:backfill-legacy-presenters
        {--session= : ID of the competition session that all legacy nominations belong to (default: match legacy sessions by name)}
        {--commit : Write the nominations (default is a preview)}';

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

        $legacySessionNames = DB::table('sessions')
            ->whereIn('id', $legacyScores->pluck('session_id')->unique()->values())
            ->pluck('name', 'id');

        $requestedSession = $this->option('session');

        if ($requestedSession !== null && ! is_numeric($requestedSession)) {
            $this->error('--session must be a competition session ID (a number).');

            return self::FAILURE;
        }

        if (is_numeric($requestedSession)) {
            // The legacy system sometimes recorded scores under whichever session
            // was active at the time, not the one they belong to. --session sends
            // every legacy nomination to one competition session instead.
            $targetId = DB::table('competition_sessions')->where('id', (int) $requestedSession)->value('id');

            if ($targetId === null) {
                $this->error("Competition session ID {$requestedSession} does not exist.");

                return self::FAILURE;
            }

            $sessionIds = $legacySessionNames->map(fn () => (int) $targetId);
        } else {
            // Same matching as inotek:import-legacy-competition: session by name.
            // Names are matched in PHP, not with a SQL join: in production the two
            // name columns use different collations, which MySQL refuses to compare.
            $targetIdsByName = DB::table('competition_sessions')->pluck('id', 'name');
            $sessionIds = $legacySessionNames
                ->map(fn ($name) => $targetIdsByName[$name] ?? null)
                ->filter();
        }

        // Project by session and code, judge by username.
        $judgeIds = DB::table('users')->whereNotNull('username')->pluck('id', 'username');

        $values = $legacyScores->countBy(fn ($row) => trim((string) $row->best_presenter));
        $updates = [];
        $unmatched = 0;
        $alreadySet = 0;
        /** @var array<string, array{0:int,1:int}> $bySession [matched, unmatched] per legacy session name */
        $bySession = [];

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

            $legacyName = (string) ($legacySessionNames[$legacy->session_id] ?? "ID {$legacy->session_id}");
            $bySession[$legacyName] ??= [0, 0];
            $bySession[$legacyName][$score === null ? 1 : 0]++;

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

        $this->line('By legacy session:');
        $this->table(['Legacy session', 'Matched', 'No match'], collect($bySession)->map(fn ($counts, $name) => [$name, ...$counts])->values()->all());

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
