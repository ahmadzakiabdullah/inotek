<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per email recipient: what was sent, to whom, and whether the mail
 * server accepted it. Used by Email & Notifications → Logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('email_logs')) {
            return;
        }

        Schema::create('email_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('event', 50)->nullable();
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->string('status', 10);
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['status', 'created_at']);
            $table->index(['event', 'created_at']);
            $table->index('recipient');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
