<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('category_rubric_mapping')
            ->select('category_id', 'rubric_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('category_id', 'rubric_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('category_rubric_mapping')
                ->where('category_id', $duplicate->category_id)
                ->where('rubric_id', $duplicate->rubric_id)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('category_rubric_mapping', function (Blueprint $table): void {
            $table->unique(['category_id', 'rubric_id']);
        });
    }

    public function down(): void
    {
        Schema::table('category_rubric_mapping', function (Blueprint $table): void {
            $table->dropUnique(['category_id', 'rubric_id']);
        });
    }
};
