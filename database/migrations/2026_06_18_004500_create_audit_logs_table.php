<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $userId = $this->userIdType();

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $blueprint) use ($userId): void {
                $blueprint->id();
                $blueprint->{$userId['blueprint']}('user_id')->nullable();
                $blueprint->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $blueprint->string('action');
                $blueprint->text('description');
                $blueprint->string('ip_address')->nullable();
                $blueprint->text('user_agent')->nullable();
                $blueprint->timestamps();
            });

            return;
        }

        // The legacy audit table stores a username/string in user_id. Preserve
        // that value under its legacy name before adding the numeric user key.
        if (Schema::hasColumn('audit_logs', 'user_id')) {
            $existingUserId = DB::selectOne(
                'SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                ['audit_logs', 'user_id'],
            );
            $existingType = strtolower(preg_replace('/\(\d+\)/', '', $existingUserId->COLUMN_TYPE ?? '') ?? '');

            if (! in_array($existingType, ['int', 'int unsigned', 'bigint', 'bigint unsigned'], true)) {
                if (Schema::hasColumn('audit_logs', 'legacy_user_id')) {
                    throw new \RuntimeException('Both user_id and legacy_user_id exist in audit_logs; inspect them before migrating.');
                }

                Schema::table('audit_logs', function (Blueprint $table): void {
                    $table->renameColumn('user_id', 'legacy_user_id');
                });
            }
        }

        if (! Schema::hasColumn('audit_logs', 'user_id')) {
            Schema::table('audit_logs', function (Blueprint $table) use ($userId): void {
                $table->{$userId['blueprint']}('user_id')->nullable()->after('id');
            });
        }

        if (! Schema::hasColumn('audit_logs', 'description')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->text('description')->nullable();
            });

            if (Schema::hasColumn('audit_logs', 'details')) {
                DB::table('audit_logs')->whereNull('description')->update(['description' => DB::raw('details')]);
            }
        }

        if (! Schema::hasColumn('audit_logs', 'ip_address')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->string('ip_address', 45)->nullable();
            });
        }

        if (! Schema::hasColumn('audit_logs', 'user_agent')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->text('user_agent')->nullable();
            });
        }

        if (! Schema::hasColumn('audit_logs', 'created_at')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasColumn('audit_logs', 'updated_at')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->timestamp('updated_at')->nullable();
            });
        }

        $hasForeignKey = DB::selectOne(
            'SELECT 1 FROM information_schema.key_column_usage WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? AND referenced_table_name = ? AND referenced_column_name = ?',
            ['audit_logs', 'user_id', 'users', 'id'],
        );

        if (! $hasForeignKey) {
            $hasOrphans = DB::table('audit_logs')
                ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
                ->whereNotNull('audit_logs.user_id')
                ->whereNull('users.id')
                ->exists();

            if (! $hasOrphans) {
                Schema::table('audit_logs', function (Blueprint $table): void {
                    $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration may have adopted legacy audit data; never drop it here.
    }

    /** @return array{blueprint: string, sql: string} */
    private function userIdType(): array
    {
        $column = DB::selectOne(
            'SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            ['users', 'id'],
        );

        $columnType = strtolower(preg_replace('/\(\d+\)/', '', $column->COLUMN_TYPE ?? '') ?? '');

        return match ($columnType) {
            'int unsigned' => ['blueprint' => 'unsignedInteger', 'sql' => 'int unsigned'],
            'bigint unsigned' => ['blueprint' => 'unsignedBigInteger', 'sql' => 'bigint unsigned'],
            'int' => ['blueprint' => 'integer', 'sql' => 'int'],
            'bigint' => ['blueprint' => 'bigInteger', 'sql' => 'bigint'],
            default => throw new \RuntimeException('Unsupported users.id type for the audit log foreign key.'),
        };
    }
};
