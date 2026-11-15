<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The production `users` table was adopted from the legacy system and still has
 * legacy columns such as `nama` that are NOT NULL without a default. The app
 * never writes them, so every registration and admin-created user failed with
 * "Field 'nama' doesn't have a default value" (seen in production on 13 Nov 2026).
 *
 * This makes every such legacy column nullable. Columns the application owns are
 * left untouched, and no data is changed. On a fresh database there are no legacy
 * columns, so this does nothing.
 */
return new class extends Migration
{
    /**
     * Columns created by this application's own migrations.
     */
    private const APP_COLUMNS = [
        'id', 'role_id', 'name', 'username', 'email', 'email_verified_at', 'password',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
        'remember_token', 'created_at', 'updated_at', 'deleted_at',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('users') || DB::getDriverName() !== 'mysql') {
            return;
        }

        $columns = DB::select(
            'SELECT COLUMN_NAME, COLUMN_TYPE, CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_COMMENT '
            .'FROM information_schema.columns '
            .'WHERE table_schema = DATABASE() AND table_name = ? '
            ."AND IS_NULLABLE = 'NO' AND COLUMN_DEFAULT IS NULL AND COLUMN_KEY <> 'PRI' "
            ."AND EXTRA NOT LIKE '%auto_increment%' AND EXTRA NOT LIKE '%GENERATED%'",
            ['users'],
        );

        foreach ($columns as $column) {
            $name = (string) $column->COLUMN_NAME;

            if (in_array(strtolower($name), self::APP_COLUMNS, true)) {
                continue;
            }

            DB::statement('ALTER TABLE `users` MODIFY '.$this->nullableDefinition($column));
        }
    }

    public function down(): void
    {
        // Keep the legacy columns nullable: rows created since then have no legacy values.
    }

    private function nullableDefinition(stdClass $column): string
    {
        $name = (string) $column->COLUMN_NAME;
        $type = (string) $column->COLUMN_TYPE;

        if (! preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new RuntimeException("Unexpected users column name [{$name}].");
        }

        // Allows types such as varchar(255), int unsigned, decimal(8,2) and enum('a','b').
        if (! preg_match("/^[a-zA-Z0-9_(), ']+$/", $type)) {
            throw new RuntimeException("Unexpected type for users.{$name}.");
        }

        $definition = "`{$name}` {$type}";
        $charset = (string) ($column->CHARACTER_SET_NAME ?? '');
        $collation = (string) ($column->COLLATION_NAME ?? '');

        if ($charset !== '' && $collation !== '') {
            if (! preg_match('/^[a-zA-Z0-9_]+$/', $charset) || ! preg_match('/^[a-zA-Z0-9_]+$/', $collation)) {
                throw new RuntimeException("Unexpected character set metadata for users.{$name}.");
            }

            $definition .= " CHARACTER SET {$charset} COLLATE {$collation}";
        }

        $definition .= ' NULL';

        $comment = (string) ($column->COLUMN_COMMENT ?? '');
        if ($comment !== '') {
            $definition .= ' COMMENT '.DB::getPdo()->quote($comment);
        }

        return $definition;
    }
};
