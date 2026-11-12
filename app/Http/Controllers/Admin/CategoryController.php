<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Project;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Display a listing of the categories.
     */
    public function index(): Response
    {
        return Inertia::render('admin/categories/index', [
            'categories' => Category::orderBy('code')->get(),
        ]);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:categories,code'],
            'name' => ['required', 'string', 'max:255'],
            'allow_team' => ['nullable', 'boolean'],
        ]);

        $validated['allow_team'] = $request->boolean('allow_team');
        Category::create($validated);

        $msg = __('Category created successfully.');
        $this->toast(['type' => 'success', 'message' => $msg]);

        return redirect()->route('admin.categories.index');
    }

    /**
     * Update the specified category in storage.
     */
    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('categories', 'code')->ignore($category->id)],
            'name' => ['required', 'string', 'max:255'],
            'allow_team' => ['nullable', 'boolean'],
        ]);

        $validated['allow_team'] = $request->boolean('allow_team');
        $category->update($validated);

        $msg = __('Category updated successfully.');
        $this->toast(['type' => 'success', 'message' => $msg]);

        return redirect()->route('admin.categories.index');
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Category $category): RedirectResponse
    {
        // Projects (and their scores) cascade on delete, so keep categories with history.
        if (Project::withTrashed()->where('category_id', $category->id)->exists()) {
            $this->toast(['type' => 'error', 'message' => __('This category has projects and cannot be deleted.')]);

            return redirect()->route('admin.categories.index');
        }

        $category->delete();

        AuditLogger::log('DELETE_CATEGORY', "Deleted category: '{$category->code}' ({$category->name})");

        $msg = __('Category deleted successfully.');
        $this->toast(['type' => 'success', 'message' => $msg]);

        return redirect()->route('admin.categories.index');
    }
}
