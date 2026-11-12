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

        Schema::create('scores', function (Blueprint $table) use ($userId): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->{$userId['blueprint']}('judge_id');
            $table->foreign('judge_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('competition_sessions')->cascadeOnDelete();
            $table->tinyInteger('round_no')->default(1);
            $table->decimal('total', 5, 2);
            $table->json('score_details');
            $table->text('comments')->nullable();
            $table->string('best_presenter')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'judge_id', 'session_id', 'round_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scores');
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
            default => throw new \RuntimeException('Unsupported users.id type for the scores judge foreign key.'),
        };
    }
};
