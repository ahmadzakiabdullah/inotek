<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CompetitionSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'categories' => Category::with('session')->orderBy('id')->get(),
            'sessions' => CompetitionSession::orderByDesc('id')->get(),
        ]);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'session_id' => ['nullable', 'exists:competition_sessions,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'allow_team' => ['nullable', 'boolean'],
        ]);

        $validated['allow_team'] = $request->boolean('allow_team');

        Category::create($validated);

        $msg = __('Category created successfully.');
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $msg]);
        } else {
            session()->flash('toast', ['type' => 'success', 'message' => $msg]);
        }

        return redirect()->route('admin.categories.index');
    }

    /**
     * Update the specified category in storage.
     */
    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'session_id' => ['nullable', 'exists:competition_sessions,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'allow_team' => ['nullable', 'boolean'],
        ]);

        $validated['allow_team'] = $request->boolean('allow_team');

        $category->update($validated);

        $msg = __('Category updated successfully.');
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $msg]);
        } else {
            session()->flash('toast', ['type' => 'success', 'message' => $msg]);
        }

        return redirect()->route('admin.categories.index');
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        $msg = __('Category deleted successfully.');
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $msg]);
        } else {
            session()->flash('toast', ['type' => 'success', 'message' => $msg]);
        }

        return redirect()->route('admin.categories.index');
    }
}
