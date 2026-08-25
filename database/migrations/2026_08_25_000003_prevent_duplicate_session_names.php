<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateIds = DB::table('competition_sessions as duplicate')
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('competition_sessions as original')
                    ->whereColumn('original.name', 'duplicate.name')
                    ->whereColumn('original.id', '<', 'duplicate.id');
            })
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('projects')->whereColumn('projects.session_id', 'duplicate.id'))
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('judge_assignments')->whereColumn('judge_assignments.session_id', 'duplicate.id'))
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('scores')->whereColumn('scores.session_id', 'duplicate.id'))
            ->pluck('duplicate.id');

        if ($duplicateIds->isNotEmpty()) {
            DB::table('competition_sessions')->whereIn('id', $duplicateIds)->delete();
        }

        Schema::table('competition_sessions', function (Blueprint $table): void {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('competition_sessions', function (Blueprint $table): void {
            $table->dropUnique(['name']);
        });
    }
};
