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

        if (! Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $table) use ($userId): void {
                $table->id();
                $table->foreignId('session_id')->constrained('competition_sessions')->cascadeOnDelete();
                $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                $table->{$userId['blueprint']}('user_id');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->string('pcode')->nullable();
                $table->string('title');
                $table->text('abstract');
                $table->string('poster_url')->nullable();
                $table->string('video_url')->nullable();
                $table->string('institution_type');
                $table->tinyInteger('status')->default(1);
                $table->string('supervisor_name');
                $table->string('supervisor_email');
                $table->string('supervisor_phone')->nullable();
                $table->text('admin_comments')->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->unique(['session_id', 'pcode']);
            });

            return;
        }

        // A prior attempt may have created the table before MySQL rejected its
        // user foreign key. Align the partial table with the actual users.id.
        $this->ensureUserForeignKey($userId);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
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
            default => throw new \RuntimeException('Unsupported users.id type for the projects user foreign key.'),
        };
    }

    /** @param array{blueprint: string, sql: string} $userId */
    private function ensureUserForeignKey(array $userId): void
    {
        $foreignKey = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.key_column_usage '
            .'WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? '
            .'AND referenced_table_name = ? AND referenced_column_name = ?',
            ['projects', 'user_id', 'users', 'id'],
        );

        $column = DB::selectOne(
            'SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            ['projects', 'user_id'],
        );
        $currentType = strtolower(preg_replace('/\(\d+\)/', '', $column->COLUMN_TYPE ?? '') ?? '');

        if ($foreignKey && $currentType !== $userId['sql']) {
            $constraint = preg_replace('/[^A-Za-z0-9_]/', '', $foreignKey->CONSTRAINT_NAME);
            DB::statement("ALTER TABLE `projects` DROP FOREIGN KEY `{$constraint}`");
            $foreignKey = null;
        }

        if ($currentType !== $userId['sql']) {
            DB::statement("ALTER TABLE `projects` MODIFY `user_id` {$userId['sql']} NOT NULL");
        }

        if (! $foreignKey) {
            $hasOrphans = DB::table('projects')
                ->leftJoin('users', 'projects.user_id', '=', 'users.id')
                ->whereNull('users.id')
                ->exists();

            if ($hasOrphans) {
                throw new \RuntimeException('Cannot add the projects user foreign key because orphaned projects exist.');
            }

            Schema::table('projects', function (Blueprint $table): void {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }
};
