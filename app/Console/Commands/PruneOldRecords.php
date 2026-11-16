<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Delete old housekeeping rows so the tables do not grow forever.
 * Audit logs, projects and scores are never touched.
 */
class PruneOldRecords extends Command
{
    public const EMAIL_LOG_DAYS = 180;

    public const READ_NOTIFICATION_DAYS = 90;

    public const FAILED_JOB_DAYS = 30;

    /** Setting holding the time and result of the last run (JSON). */
    public const SETTING_LAST_RUN = 'maintenance_last_pruned';

    protected $signature = 'inotek:prune {--dry-run : Only count what would be deleted}';

    protected $description = 'Delete old email logs, read notifications and failed jobs';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $counts = [
            'email_logs' => $this->prune('email_logs', 'created_at', self::EMAIL_LOG_DAYS, $dryRun),
            'notifications' => $this->prune('notifications', 'created_at', self::READ_NOTIFICATION_DAYS, $dryRun, fn ($q) => $q->whereNotNull('read_at')),
            'failed_jobs' => $this->prune('failed_jobs', 'failed_at', self::FAILED_JOB_DAYS, $dryRun),
        ];

        foreach ($counts as $table => $count) {
            $this->line(sprintf('%s: %d %s', $table, $count, $dryRun ? 'would be deleted' : 'deleted'));
        }

        if (! $dryRun) {
            Setting::set(self::SETTING_LAST_RUN, (string) json_encode([
                'at' => now()->toIso8601String(),
                'deleted' => $counts,
            ]), 'json');
        }

        return self::SUCCESS;
    }

    /**
     * @param  (callable(Builder): mixed)|null  $scope
     */
    private function prune(string $table, string $column, int $days, bool $dryRun, ?callable $scope = null): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table)->where($column, '<', now()->subDays($days));

        if ($scope) {
            $scope($query);
        }

        return $dryRun ? $query->count() : $query->delete();
    }

    /**
     * @return array{at: string, deleted: array<string, int>}|null
     */
    public static function lastRun(): ?array
    {
        $saved = json_decode((string) Setting::get(self::SETTING_LAST_RUN, ''), true);

        if (! is_array($saved) || ! is_string($saved['at'] ?? null)) {
            return null;
        }

        $deleted = [];

        foreach ((array) ($saved['deleted'] ?? []) as $table => $count) {
            $deleted[(string) $table] = (int) $count;
        }

        return ['at' => $saved['at'], 'deleted' => $deleted];
    }
}
