<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy categories may not have a session assignment yet. Keep them
        // until an explicit data migration maps them; a missing project
        // reference alone does not make a category safe to delete.
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
