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
        Schema::table('rubric_items', function (Blueprint $table) {
            $table->string('section')->nullable()->after('rubric_id');
            $table->string('code')->nullable()->after('section');
            $table->text('description')->nullable()->after('criteria_name');
            $table->json('scale_descriptions')->nullable()->after('max_points');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rubric_items', function (Blueprint $table) {
            $table->dropColumn(['section', 'code', 'description', 'scale_descriptions']);
        });
    }
};
