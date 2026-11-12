<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('category_rubric_mapping')
            || ! Schema::hasColumn('category_rubric_mapping', 'category_id')) {
            return;
        }

        if (! Schema::hasColumn('category_rubric_mapping', 'session_id')) {
            Schema::table('category_rubric_mapping', function (Blueprint $table): void {
                $table->foreignId('session_id')->nullable()->after('category_id')
                    ->constrained('competition_sessions')->cascadeOnDelete();
            });
        }

        $indexes = collect(Schema::getIndexes('category_rubric_mapping'));
        $oldUnique = $indexes->first(fn (array $index): bool =>
            ($index['unique'] ?? false)
            && ($index['columns'] ?? []) === ['category_id', 'rubric_id']
        );
        if ($oldUnique) {
            // MySQL keeps the category_id foreign key on the old unique index, so
            // give the key its own index before dropping the unique constraint.
            $hasCategoryIndex = $indexes->contains(fn (array $index): bool =>
                ($index['columns'][0] ?? null) === 'category_id' && $index['name'] !== $oldUnique['name']
            );
            Schema::table('category_rubric_mapping', function (Blueprint $table) use ($hasCategoryIndex): void {
                if (! $hasCategoryIndex) {
                    $table->index('category_id');
                }
                $table->dropUnique(['category_id', 'rubric_id']);
            });
        }

        $sessions = DB::table('competition_sessions')->orderBy('id')->pluck('id');
        $mappingsByCategory = DB::table('category_rubric_mapping')
            ->whereNull('session_id')
            ->whereNotNull('category_id')
            ->orderBy('category_id')
            ->orderByDesc('rubric_id')
            ->get()
            ->groupBy('category_id');

        foreach ($sessions as $sessionIndex => $sessionId) {
            foreach ($mappingsByCategory as $categoryId => $mappings) {
                // Scoring selected the highest-ID rubric for a category. Keep
                // that effective mapping as the session default.
                $mapping = $mappings->first();

                if ($sessionIndex === 0) {
                    DB::table('category_rubric_mapping')
                        ->where('id', $mapping->id)
                        ->update(['session_id' => $sessionId]);
                } else {
                    $values = (array) $mapping;
                    unset($values['id']);
                    $values['session_id'] = $sessionId;
                    if (Schema::hasColumn('category_rubric_mapping', 'category_code')) {
                        $values['category_code'] = null;
                    }
                    DB::table('category_rubric_mapping')->insert($values);
                }

                DB::table('category_rubric_mapping')
                    ->whereNull('session_id')
                    ->where('category_id', $categoryId)
                    ->where('id', '!=', $mapping->id)
                    ->delete();
            }
        }

        if ($sessions->isNotEmpty()) {
            DB::table('category_rubric_mapping')->whereNull('session_id')->delete();
        }

        $indexes = collect(Schema::getIndexes('category_rubric_mapping'));
        $sessionCategoryUnique = $indexes->contains(fn (array $index): bool =>
            ($index['unique'] ?? false)
            && ($index['columns'] ?? []) === ['session_id', 'category_id']
        );
        if (! $sessionCategoryUnique) {
            Schema::table('category_rubric_mapping', function (Blueprint $table): void {
                $table->unique(['session_id', 'category_id']);
            });
        }
    }

    public function down(): void
    {
        // Session-specific assignments may differ; merging them back into one
        // global mapping would discard information, so retain the migrated rows.
    }
};
