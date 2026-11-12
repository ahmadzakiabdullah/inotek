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
        if (! Schema::hasTable('rubrics')) {
            Schema::create('rubrics', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('rubrics', 'updated_at')) {
            Schema::table('rubrics', function (Blueprint $table): void {
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('rubric_items')) {
            Schema::create('rubric_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('rubric_id')->constrained('rubrics')->cascadeOnDelete();
                $table->string('criteria_name');
                $table->decimal('weight', 4, 2);
                $table->integer('max_points')->default(5);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('category_rubric_mapping')) {
            Schema::create('category_rubric_mapping', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                $table->foreignId('rubric_id')->constrained('rubrics')->cascadeOnDelete();
                $table->timestamps();
            });

            return;
        }

        // Older INOTEK stores mappings by category code. Add the normalized
        // key while retaining the legacy code and its existing rows.
        $addCategoryId = ! Schema::hasColumn('category_rubric_mapping', 'category_id');
        $addCreatedAt = ! Schema::hasColumn('category_rubric_mapping', 'created_at');

        if ($addCategoryId || $addCreatedAt) {
            Schema::table('category_rubric_mapping', function (Blueprint $table) use ($addCategoryId, $addCreatedAt): void {
                if ($addCategoryId) {
                    $table->foreignId('category_id')->nullable()->after('id');
                }

                if ($addCreatedAt) {
                    $table->timestamp('created_at')->nullable();
                }
            });
        }

        if (Schema::hasColumn('category_rubric_mapping', 'category_id')
            && Schema::hasColumn('category_rubric_mapping', 'category_code')) {
            DB::statement(
                'UPDATE `category_rubric_mapping` AS `mapping` '
                .'INNER JOIN `categories` AS `category` '
                .'ON CONVERT(`category`.`code` USING utf8mb4) COLLATE utf8mb4_unicode_ci '
                .'= CONVERT(`mapping`.`category_code` USING utf8mb4) COLLATE utf8mb4_unicode_ci '
                .'SET `mapping`.`category_id` = `category`.`id` '
                .'WHERE `mapping`.`category_id` IS NULL'
            );

            // New rows are written by category_id. Make the legacy-only field
            // optional so its old NOT NULL constraint does not reject them.
            DB::statement(
                'ALTER TABLE `category_rubric_mapping` '
                .'MODIFY `category_code` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // These tables may contain legacy rubrics and mappings; retain them on rollback.
    }
};
