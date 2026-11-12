<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('competition_sessions', 'session_starts_at')) {
                $table->date('session_starts_at')->nullable();
            }
            if (! Schema::hasColumn('competition_sessions', 'session_ends_at')) {
                $table->date('session_ends_at')->nullable();
            }
            if (! Schema::hasColumn('competition_sessions', 'registration_opens_at')) {
                $table->date('registration_opens_at')->nullable();
            }
            if (! Schema::hasColumn('competition_sessions', 'registration_closes_at')) {
                $table->date('registration_closes_at')->nullable();
            }
            if (! Schema::hasColumn('competition_sessions', 'registration_mode')) {
                $table->string('registration_mode', 16)->default('scheduled');
            }
        });

        if (Schema::hasColumn('competition_sessions', 'registration_open')) {
            DB::table('competition_sessions')->where('registration_open', true)
                ->update(['registration_mode' => 'open']);
            DB::table('competition_sessions')->where('registration_open', false)
                ->update(['registration_mode' => 'closed']);

            Schema::table('competition_sessions', function (Blueprint $table): void {
                $table->dropColumn('registration_open');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('competition_sessions', 'registration_open')) {
            Schema::table('competition_sessions', function (Blueprint $table): void {
                $table->boolean('registration_open')->default(false);
            });
        }

        DB::table('competition_sessions')
            ->where('registration_mode', 'open')
            ->update(['registration_open' => true]);

        Schema::table('competition_sessions', function (Blueprint $table): void {
            $table->dropColumn([
                'session_starts_at',
                'session_ends_at',
                'registration_opens_at',
                'registration_closes_at',
                'registration_mode',
            ]);
        });
    }
};
