<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scores', function (Blueprint $table): void {
            $table->index(['session_id', 'round_no']);
            $table->index(['project_id', 'round_no']);
        });

        Schema::table('judge_assignments', function (Blueprint $table): void {
            $table->index(['session_id', 'round_no']);
            $table->index(['judge_id', 'session_id', 'round_no']);
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index(['action', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('scores', function (Blueprint $table): void {
            $table->dropIndex(['scores_session_id_round_no_index']);
            $table->dropIndex(['scores_project_id_round_no_index']);
        });

        Schema::table('judge_assignments', function (Blueprint $table): void {
            $table->dropIndex(['judge_assignments_session_id_round_no_index']);
            $table->dropIndex(['judge_assignments_judge_id_session_id_round_no_index']);
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['audit_logs_action_created_at_index']);
            $table->dropIndex(['audit_logs_user_id_created_at_index']);
        });
    }
};
