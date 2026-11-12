<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $addSection = ! Schema::hasColumn('rubric_items', 'section');
        $addCode = ! Schema::hasColumn('rubric_items', 'code');
        $addCriteriaName = ! Schema::hasColumn('rubric_items', 'criteria_name');
        $addDescription = ! Schema::hasColumn('rubric_items', 'description');
        $addMaxPoints = ! Schema::hasColumn('rubric_items', 'max_points');
        $addScaleDescriptions = ! Schema::hasColumn('rubric_items', 'scale_descriptions');
        $addCreatedAt = ! Schema::hasColumn('rubric_items', 'created_at');
        $addUpdatedAt = ! Schema::hasColumn('rubric_items', 'updated_at');

        if ($addSection || $addCode || $addCriteriaName || $addDescription || $addMaxPoints || $addScaleDescriptions || $addCreatedAt || $addUpdatedAt) {
            Schema::table('rubric_items', function (Blueprint $table) use (
                $addSection,
                $addCode,
                $addCriteriaName,
                $addDescription,
                $addMaxPoints,
                $addScaleDescriptions,
                $addCreatedAt,
                $addUpdatedAt,
            ): void {
                if ($addSection) {
                    $table->string('section')->nullable();
                }

                if ($addCode) {
                    $table->string('code')->nullable();
                }

                if ($addCriteriaName) {
                    $table->string('criteria_name')->nullable();
                }

                if ($addDescription) {
                    $table->text('description')->nullable();
                }

                if ($addMaxPoints) {
                    $table->integer('max_points')->default(5);
                }

                if ($addScaleDescriptions) {
                    $table->json('scale_descriptions')->nullable();
                }

                if ($addCreatedAt) {
                    $table->timestamp('created_at')->nullable();
                }

                if ($addUpdatedAt) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
        }

        if ($addCriteriaName && Schema::hasColumn('rubric_items', 'label')) {
            DB::table('rubric_items')->whereNull('criteria_name')->update(['criteria_name' => DB::raw('label')]);

            // Legacy label was NOT NULL; current writes use criteria_name.
            DB::statement('ALTER TABLE `rubric_items` MODIFY `label` VARCHAR(255) NULL');
        }
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
