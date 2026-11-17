<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Projects are soft-deleted, and until now their judge assignments stayed
 * behind. That crashed the admin dashboard after the Sesi 2/2026 test project
 * was deleted. New deletions now remove their assignments (Project::booted);
 * this clears the ones left over from earlier deletions. Scores are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('judge_assignments')
            ->whereIn('project_id', DB::table('projects')->whereNotNull('deleted_at')->select('id'))
            ->delete();
    }

    public function down(): void
    {
        // The removed assignments belonged to deleted projects and cannot be judged.
    }
};
