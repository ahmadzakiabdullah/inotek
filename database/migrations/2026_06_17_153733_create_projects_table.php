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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('competition_sessions')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('pcode')->nullable();
            $table->string('title');
            $table->text('abstract');
            $table->string('poster_url')->nullable();
            $table->string('video_url')->nullable();
            $table->string('institution_type'); // 'utem' or 'ipt'
            $table->tinyInteger('status')->default(1); // 1 = STATUS_NEW
            $table->string('supervisor_name');
            $table->string('supervisor_email');
            $table->string('supervisor_phone')->nullable();
            $table->text('admin_comments')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['session_id', 'pcode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
