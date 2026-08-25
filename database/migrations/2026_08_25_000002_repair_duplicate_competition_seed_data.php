<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $activeSessions = DB::table('competition_sessions')
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('id');

        if ($activeSessions->count() > 1) {
            DB::table('competition_sessions')
                ->whereIn('id', $activeSessions->skip(1)->all())
                ->update(['is_active' => false]);
        }

        $standardRubricId = DB::table('rubrics')
            ->where('name', 'Standard Rubric')
            ->orderBy('id')
            ->value('id');

        if (!$standardRubricId) {
            return;
        }

        $categoryIds = DB::table('categories')
            ->whereIn('code', ['C1', 'C2', 'C3', 'C4', 'C6'])
            ->pluck('id');

        foreach ($categoryIds as $categoryId) {
            DB::table('category_rubric_mapping')->insertOrIgnore([
                'category_id' => $categoryId,
                'rubric_id' => $standardRubricId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Data repair is intentionally not reversed to avoid reintroducing
        // duplicate active sessions or removing valid rubric mappings.
    }
};
