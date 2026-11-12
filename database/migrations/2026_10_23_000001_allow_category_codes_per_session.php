<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = DB::select(
            'SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS indexed_columns '
            .'FROM information_schema.statistics '
            .'WHERE table_schema = DATABASE() AND table_name = ? AND non_unique = 0 '
            .'GROUP BY INDEX_NAME',
            ['categories'],
        );

        foreach ($indexes as $index) {
            if ($index->INDEX_NAME === 'PRIMARY' || $index->indexed_columns !== 'code') {
                continue;
            }

            $indexName = preg_replace('/[^A-Za-z0-9_]/', '', $index->INDEX_NAME);
            DB::statement("ALTER TABLE `categories` DROP INDEX `{$indexName}`");
        }
    }

    public function down(): void
    {
        // Per-session category codes may already contain duplicates globally.
        // Do not restore the legacy global unique index on rollback.
    }
};
