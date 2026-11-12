<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs') || ! Schema::hasColumn('audit_logs', 'legacy_user_id')) {
            return;
        }

        $column = DB::selectOne(
            'SELECT COLUMN_TYPE, IS_NULLABLE, CHARACTER_SET_NAME, COLLATION_NAME '
            .'FROM information_schema.columns '
            .'WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            ['audit_logs', 'legacy_user_id'],
        );

        if (! $column || $column->IS_NULLABLE === 'YES') {
            return;
        }

        $type = (string) $column->COLUMN_TYPE;
        if (! preg_match('/^[a-zA-Z0-9_(), ]+$/', $type)) {
            throw new RuntimeException('Unexpected audit_logs.legacy_user_id column type.');
        }

        $definition = "`legacy_user_id` {$type}";
        $charset = (string) ($column->CHARACTER_SET_NAME ?? '');
        $collation = (string) ($column->COLLATION_NAME ?? '');

        if ($charset !== '' && $collation !== '') {
            if (! preg_match('/^[a-zA-Z0-9_]+$/', $charset) || ! preg_match('/^[a-zA-Z0-9_]+$/', $collation)) {
                throw new RuntimeException('Unexpected audit_logs.legacy_user_id character set metadata.');
            }

            $definition .= " CHARACTER SET {$charset} COLLATE {$collation}";
        }

        $definition .= ' NULL';

        DB::statement("ALTER TABLE `audit_logs` MODIFY {$definition}");
    }

    public function down(): void
    {
        // Keep the column nullable because new audit records legitimately have no legacy user ID.
    }
};
