<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Projects an admin adds to Round 2 on top of the automatic Round 1 shortlist,
 * chosen from Best Presenter nominees at the organizer's request.
 */
return new class extends Migration
{
    public function up(): void
    {
        $userId = $this->userIdType();

        Schema::create('round2_wildcards', function (Blueprint $table) use ($userId): void {
            $table->id();
            $table->foreignId('session_id')->constrained('competition_sessions')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->text('reason');
            $table->{$userId['blueprint']}('added_by')->nullable();
            $table->foreign('added_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['session_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round2_wildcards');
    }

    /** @return array{blueprint: string} */
    private function userIdType(): array
    {
        $column = DB::getDriverName() === 'mysql'
            ? DB::selectOne(
                'SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                ['users', 'id'],
            )
            : null;

        $columnType = strtolower(preg_replace('/\(\d+\)/', '', $column->COLUMN_TYPE ?? 'bigint unsigned') ?? '');

        return match ($columnType) {
            'int unsigned' => ['blueprint' => 'unsignedInteger'],
            'bigint unsigned' => ['blueprint' => 'unsignedBigInteger'],
            'int' => ['blueprint' => 'integer'],
            'bigint' => ['blueprint' => 'bigInteger'],
            default => throw new RuntimeException('Unsupported users.id type for the Round 2 wildcards foreign key.'),
        };
    }
};
