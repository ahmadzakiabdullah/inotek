<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure Laravel's HTTP session table has the columns required by the
     * database session handler. The legacy `sessions` table belongs to INOTEK
     * competition data, so never alter or clear it here.
     */
    public function up(): void
    {
        $tableName = 'laravel_sessions';

        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });

            return;
        }

        $missingUserId = ! Schema::hasColumn($tableName, 'user_id');
        $missingIpAddress = ! Schema::hasColumn($tableName, 'ip_address');
        $missingUserAgent = ! Schema::hasColumn($tableName, 'user_agent');
        $missingPayload = ! Schema::hasColumn($tableName, 'payload');
        $missingLastActivity = ! Schema::hasColumn($tableName, 'last_activity');

        if ($missingUserId || $missingIpAddress || $missingUserAgent || $missingPayload || $missingLastActivity) {
            Schema::table($tableName, function (Blueprint $table) use (
                $missingUserId,
                $missingIpAddress,
                $missingUserAgent,
                $missingPayload,
                $missingLastActivity,
            ): void {
                if ($missingUserId) {
                    $table->foreignId('user_id')->nullable()->index();
                }

                if ($missingIpAddress) {
                    $table->string('ip_address', 45)->nullable();
                }

                if ($missingUserAgent) {
                    $table->text('user_agent')->nullable();
                }

                // Nullable additions preserve any legacy session rows safely.
                if ($missingPayload) {
                    $table->longText('payload')->nullable();
                }

                if ($missingLastActivity) {
                    $table->integer('last_activity')->nullable()->index();
                }
            });
        }

        // Preserve existing Laravel session rows; incomplete/expired sessions
        // are harmless and will be cleaned up by Laravel's session lottery.
    }

    /**
     * Keep repaired session columns on rollback so the running app stays usable.
     */
    public function down(): void
    {
        // This repair migration is intentionally irreversible.
    }
};
