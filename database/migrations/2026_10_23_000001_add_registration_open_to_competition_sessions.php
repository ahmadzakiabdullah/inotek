<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('competition_sessions', 'registration_open')) {
            Schema::table('competition_sessions', function (Blueprint $table): void {
                $table->boolean('registration_open')->default(false)->after('is_active');
            });
        }
    }

    public function down(): void
    {
        Schema::table('competition_sessions', function (Blueprint $table): void {
            $table->dropColumn('registration_open');
        });
    }
};
