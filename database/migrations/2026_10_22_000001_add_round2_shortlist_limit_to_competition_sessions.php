<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_sessions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('round2_shortlist_limit')->default(3);
        });
    }

    public function down(): void
    {
        Schema::table('competition_sessions', function (Blueprint $table): void {
            $table->dropColumn('round2_shortlist_limit');
        });
    }
};
