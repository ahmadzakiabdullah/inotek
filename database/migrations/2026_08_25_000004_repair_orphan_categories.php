<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')
            ->whereNull('session_id')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('projects')->whereColumn('projects.category_id', 'categories.id'))
            ->delete();

        Schema::table('categories', function (Blueprint $table): void {
            $table->unique(['session_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropUnique(['session_id', 'code']);
        });
    }
};
