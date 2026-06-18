<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Rubric;
use App\Models\RubricItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RubricController extends Controller
{
    /**
     * Display a listing of the rubrics.
     */
    public function index(): Response
    {
        return Inertia::render('admin/rubrics/index', [
            'rubrics' => Rubric::with(['items', 'categories.session'])->orderBy('id')->get(),
            'categories' => Category::with('session')->orderBy('id')->get(),
        ]);
    }

    /**
     * Store a newly created rubric in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
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
            'category_ids.*' => ['exists:categories,id'],
        ]);

        $items = $request->input('items', []);

        DB::transaction(function () use ($request, $items) {
            $rubric = Rubric::create([
                'name' => $request->input('name'),
                'description' => $request->input('description'),
            ]);

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

            if ($request->has('category_ids')) {
                $rubric->categories()->sync($request->input('category_ids'));
            }

            \App\Services\AuditLogger::log(
                'CREATE_RUBRIC',
                "Created rubric: '{$rubric->name}'"
            );
        });

        $msg = __('Rubric created successfully.');
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $msg]);
        } else {
            session()->flash('toast', ['type' => 'success', 'message' => $msg]);
        }

        return redirect()->route('admin.rubrics.index');
    }

    /**
     * Update the specified rubric in storage.
     */
    public function update(Request $request, Rubric $rubric): RedirectResponse
    {
        $request->validate([
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
            'category_ids.*' => ['exists:categories,id'],
        ]);

        $items = $request->input('items', []);

        DB::transaction(function () use ($request, $rubric, $items) {
            $rubric->update([
                'name' => $request->input('name'),
                'description' => $request->input('description'),
            ]);

            // Re-create items (safest approach for CRUD)
            $rubric->items()->delete();
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

            $rubric->categories()->sync($request->input('category_ids', []));

            \App\Services\AuditLogger::log(
                'UPDATE_RUBRIC',
                "Updated rubric: '{$rubric->name}'"
            );
        });

        $msg = __('Rubric updated successfully.');
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $msg]);
        } else {
            session()->flash('toast', ['type' => 'success', 'message' => $msg]);
        }

        return redirect()->route('admin.rubrics.index');
    }

    /**
     * Remove the specified rubric from storage.
     */
    public function destroy(Rubric $rubric): RedirectResponse
    {
        $name = $rubric->name;
        $rubric->delete();

        \App\Services\AuditLogger::log(
            'DELETE_RUBRIC',
            "Deleted rubric: '{$name}'"
        );

        $msg = __('Rubric deleted successfully.');
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $msg]);
        } else {
            session()->flash('toast', ['type' => 'success', 'message' => $msg]);
        }

        return redirect()->route('admin.rubrics.index');
    }
}
