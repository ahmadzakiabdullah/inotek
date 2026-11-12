<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryRubricMapping;
use App\Models\CompetitionSession;
use App\Models\Rubric;
use App\Models\RubricItem;
use App\Models\Score;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RubricController extends Controller
{
    public function index(Request $request): Response
    {
        $sessions = CompetitionSession::query()
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get(['id', 'name', 'is_active']);

        $selectedSessionId = $request->integer('session_id');
        if (! $sessions->contains('id', $selectedSessionId)) {
            $selectedSessionId = (int) ($sessions->firstWhere('is_active', true)?->id
                ?? $sessions->first()?->id
                ?? 0);
        }

        $mappings = DB::table('category_rubric_mapping')
            ->where('session_id', $selectedSessionId)
            ->get(['category_id', 'rubric_id']);
        $categoriesById = Category::query()
            ->whereIn('id', $mappings->pluck('category_id')->unique())
            ->orderBy('code')
            ->get()
            ->keyBy('id');
        $assignedRubricIds = $mappings->pluck('rubric_id')->unique();
        $rubrics = Rubric::query()
            ->with('items')
            ->whereIn('id', $assignedRubricIds)
            ->orWhereDoesntHave('categories')
            ->orderBy('id')
            ->get();

        $rubrics->each(function (Rubric $rubric) use ($mappings, $categoriesById): void {
            $categoryIds = $mappings
                ->where('rubric_id', $rubric->id)
                ->pluck('category_id');
            $rubric->setRelation(
                'categories',
                $categoryIds->map(fn ($id) => $categoriesById->get($id))->filter()->values()
            );
        });

        return Inertia::render('admin/rubrics/index', [
            'rubrics' => $rubrics,
            'categories' => Category::orderBy('code')->get(),
            'sessions' => $sessions,
            'selectedSessionId' => $selectedSessionId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $sessionId = (int) $validated['session_id'];

        DB::transaction(function () use ($validated, $sessionId): void {
            $rubric = Rubric::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            $this->createItems($rubric, $validated['items']);
            $this->replaceSessionMappings($rubric, $sessionId, $validated['category_ids'] ?? []);

            \App\Services\AuditLogger::log('CREATE_RUBRIC', "Created rubric: '{$rubric->name}' for session {$sessionId}");
        });

        $this->flash('Rubric created successfully.');

        return redirect()->route('admin.rubrics.index', ['session_id' => $sessionId]);
    }

    public function update(Request $request, Rubric $rubric): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $sessionId = (int) $validated['session_id'];

        DB::transaction(function () use ($validated, $rubric, $sessionId): void {
            $hasScores = $this->hasScoresForRubric($rubric);
            $usedByOtherSessions = DB::table('category_rubric_mapping')
                ->where('rubric_id', $rubric->id)
                ->where('session_id', '!=', $sessionId)
                ->exists();
            $targetRubric = $rubric;

            if ($hasScores || $usedByOtherSessions) {
                $targetRubric = Rubric::create([
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                ]);
                DB::table('category_rubric_mapping')
                    ->where('session_id', $sessionId)
                    ->where('rubric_id', $rubric->id)
                    ->delete();
                $stillAssigned = DB::table('category_rubric_mapping')
                    ->where('rubric_id', $rubric->id)
                    ->exists();
                if (! $stillAssigned && $hasScores && ! str_ends_with($rubric->name, ' (Archived)')) {
                    $rubric->update(['name' => mb_substr($rubric->name, 0, 244).' (Archived)']);
                }
            } else {
                $rubric->update([
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                ]);
                $rubric->items()->delete();
            }

            $this->createItems($targetRubric, $validated['items']);
            $this->replaceSessionMappings($targetRubric, $sessionId, $validated['category_ids'] ?? []);

            \App\Services\AuditLogger::log(
                'UPDATE_RUBRIC',
                "Updated rubric '{$targetRubric->name}' for session {$sessionId}"
            );
        });

        $this->flash('Rubric updated successfully.');

        return redirect()->route('admin.rubrics.index', ['session_id' => $sessionId]);
    }

    public function destroy(Request $request, Rubric $rubric): RedirectResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'integer', 'exists:competition_sessions,id'],
        ]);
        $sessionId = (int) $validated['session_id'];

        DB::transaction(function () use ($rubric, $sessionId): void {
            DB::table('category_rubric_mapping')
                ->where('session_id', $sessionId)
                ->where('rubric_id', $rubric->id)
                ->delete();

            $stillAssigned = DB::table('category_rubric_mapping')
                ->where('rubric_id', $rubric->id)
                ->exists();
            if ($stillAssigned) {
                return;
            }

            if ($this->hasScoresForRubric($rubric)) {
                if (! str_ends_with($rubric->name, ' (Archived)')) {
                    $rubric->update(['name' => mb_substr($rubric->name, 0, 244).' (Archived)']);
                }

                return;
            }

            $rubric->delete();
        });

        \App\Services\AuditLogger::log(
            'UNASSIGN_RUBRIC',
            "Removed rubric: '{$rubric->name}' from session {$sessionId}"
        );

        $this->flash('Rubric removed from this session.');

        return redirect()->route('admin.rubrics.index', ['session_id' => $sessionId]);
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'session_id' => ['required', 'integer', 'exists:competition_sessions,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.section' => ['nullable', 'string', 'max:255'],
            'items.*.code' => ['nullable', 'string', 'max:255'],
            'items.*.criteria_name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.weight' => ['required', 'numeric', 'min:0.01', 'max:1.00'],
            'items.*.max_points' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.scale_descriptions' => ['nullable', 'array'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ];
    }

    /** @param array<int, array<string, mixed>> $items */
    private function createItems(Rubric $rubric, array $items): void
    {
        foreach ($items as $item) {
            RubricItem::create([
                'rubric_id' => $rubric->id,
                'section' => $item['section'] ?? null,
                'code' => $item['code'] ?? null,
                'criteria_name' => $item['criteria_name'],
                'description' => $item['description'] ?? null,
                'weight' => $item['weight'],
                'max_points' => $item['max_points'] ?? 5,
                'scale_descriptions' => $item['scale_descriptions'] ?? null,
            ]);
        }
    }

    /** @param array<int, int> $categoryIds */
    private function replaceSessionMappings(Rubric $rubric, int $sessionId, array $categoryIds): void
    {
        DB::table('category_rubric_mapping')
            ->where('session_id', $sessionId)
            ->where('rubric_id', $rubric->id)
            ->delete();

        foreach (array_unique(array_map('intval', $categoryIds)) as $categoryId) {
            DB::table('category_rubric_mapping')
                ->where('session_id', $sessionId)
                ->where('category_id', $categoryId)
                ->delete();

            CategoryRubricMapping::create([
                'session_id' => $sessionId,
                'category_id' => $categoryId,
                'rubric_id' => $rubric->id,
            ]);
        }
    }

    private function hasScoresForRubric(Rubric $rubric): bool
    {
        $itemIds = $rubric->items()->pluck('id')->map(fn ($id) => (string) $id)->all();
        if ($itemIds === []) {
            return false;
        }

        // Stream scores in chunks and stop at the first match instead of loading every score.
        return Score::query()->select(['id', 'score_details'])->lazyById(500)->contains(
            fn (Score $score) => array_intersect($itemIds, array_map('strval', array_keys($score->score_details ?? []))) !== []
        );
    }

    private function flash(string $message): void
    {
        $this->toast(['type' => 'success', 'message' => __($message)]);
    }
}