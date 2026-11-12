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

        if (! Schema::hasTable('passkeys')) {
            Schema::create('passkeys', function (Blueprint $table) use ($userId): void {
                $table->id();
                $table->{$userId['blueprint']}('user_id')->index();
                $table->string('name');
                $table->string('credential_id')->unique();
                $table->json('credential');
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }

        $existingUserId = DB::selectOne(
            'SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            ['passkeys', 'user_id'],
        );

        if (! $existingUserId) {
            Schema::table('passkeys', function (Blueprint $table) use ($userId): void {
                $table->{$userId['blueprint']}('user_id')->index();
            });
        } elseif (strtolower($existingUserId->COLUMN_TYPE) !== $userId['sql']) {
            // Older deployments can have an int users.id while this migration
            // originally declared user_id as bigint. Match the real parent type.
            DB::statement("ALTER TABLE `passkeys` MODIFY `user_id` {$userId['sql']} NOT NULL");
        }

        $hasForeignKey = DB::selectOne(
            'SELECT 1 FROM information_schema.key_column_usage WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? AND referenced_table_name = ? AND referenced_column_name = ?',
            ['passkeys', 'user_id', 'users', 'id'],
        );

        if (! $hasForeignKey) {
            $hasOrphanedKeys = DB::table('passkeys')
                ->leftJoin('users', 'passkeys.user_id', '=', 'users.id')
                ->whereNull('users.id')
                ->exists();

            if ($hasOrphanedKeys) {
                throw new \RuntimeException('Cannot add the passkeys user foreign key because orphaned passkeys exist. Resolve those rows before rerunning migrations.');
            }

            Schema::table('passkeys', function (Blueprint $table): void {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passkeys');
    }

    /**
     * Return a safe Blueprint method and SQL type matching users.id.
     *
     * @return array{blueprint: string, sql: string}
     */
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
            default => throw new \RuntimeException('Unsupported users.id type for the passkeys foreign key.'),
        };
    }
};
