<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('session_id')->nullable()->constrained('competition_sessions')->nullOnDelete();
                $table->string('code');
                $table->string('name');
                $table->timestamps();
            });

            return;
        }

        // Preserve legacy category columns and rows, adding only the session
        // relationship required by the normalized competition schema.
        if (! Schema::hasColumn('categories', 'session_id')) {
            Schema::table('categories', function (Blueprint $table): void {
                $table->foreignId('session_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('competition_sessions')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration may adopt pre-existing category data; retain it on rollback.
    }
};
