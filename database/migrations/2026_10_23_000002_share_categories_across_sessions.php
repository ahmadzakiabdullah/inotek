<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            // Keep one canonical category per code and redirect every project
            // and rubric mapping before removing duplicate session copies.
            $categories = DB::table('categories')->orderBy('id')->get();
            $canonicalByCode = [];

            foreach ($categories as $category) {
                $key = mb_strtolower(trim($category->code));
                if (! isset($canonicalByCode[$key])) {
                    $canonicalByCode[$key] = $category;
                    continue;
                }

                $canonical = $canonicalByCode[$key];

                if (Schema::hasColumn('categories', 'allow_team') && $category->allow_team) {
                    DB::table('categories')->where('id', $canonical->id)
                        ->update(['allow_team' => true]);
                }

                DB::table('projects')->where('category_id', $category->id)
                    ->update(['category_id' => $canonical->id]);

                if (Schema::hasTable('category_rubric_mapping')) {
                    $mappingRubricIds = DB::table('category_rubric_mapping')
                        ->where('category_id', $category->id)
                        ->pluck('rubric_id');

                    foreach ($mappingRubricIds as $rubricId) {
                        $alreadyMapped = DB::table('category_rubric_mapping')
                            ->where('category_id', $canonical->id)
                            ->where('rubric_id', $rubricId)
                            ->exists();

                        if ($alreadyMapped) {
                            DB::table('category_rubric_mapping')
                                ->where('category_id', $category->id)
                                ->where('rubric_id', $rubricId)
                                ->delete();
                        } else {
                            DB::table('category_rubric_mapping')
                                ->where('category_id', $category->id)
                                ->where('rubric_id', $rubricId)
                                ->update(['category_id' => $canonical->id]);
                        }
                    }
                }

                DB::table('categories')->where('id', $category->id)->delete();
            }

            DB::table('categories')->update(['session_id' => null]);
        });

        $indexes = DB::select(
            'SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS indexed_columns '
            .'FROM information_schema.statistics '
            .'WHERE table_schema = DATABASE() AND table_name = ? AND non_unique = 0 '
            .'GROUP BY INDEX_NAME',
            ['categories'],
        );

        foreach ($indexes as $index) {
            if ($index->INDEX_NAME === 'PRIMARY' || $index->indexed_columns !== 'session_id,code') {
                continue;
            }

            // MySQL uses the composite index to support the session_id
            // foreign key. Add a standalone index first so the FK remains
            // valid when the per-session unique index is removed.
            $sessionIndexExists = collect(DB::select(
                'SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS indexed_columns '
                .'FROM information_schema.statistics '
                .'WHERE table_schema = DATABASE() AND table_name = ? AND non_unique = 1 '
                .'GROUP BY INDEX_NAME',
                ['categories'],
            ))->contains(fn ($existingIndex) => $existingIndex->indexed_columns === 'session_id');

            if (! $sessionIndexExists) {
                Schema::table('categories', function (Blueprint $table): void {
                    $table->index('session_id');
                });
            }

            $indexName = preg_replace('/[^A-Za-z0-9_]/', '', $index->INDEX_NAME);
            DB::statement("ALTER TABLE `categories` DROP INDEX `{$indexName}`");
        }

        Schema::table('categories', function (Blueprint $table): void {
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->unique(['session_id', 'code']);
        });

        // Merged category rows cannot be split back into their original
        // session-specific identities without an archived copy of the data.
    }
};
