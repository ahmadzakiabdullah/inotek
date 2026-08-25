<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompetitionSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CompetitionSessionController extends Controller
{
    /**
     * Display a listing of the sessions.
     */
    public function index(): Response
    {
        return Inertia::render('admin/sessions/index', [
            'sessions' => CompetitionSession::withCount('categories')->orderByDesc('id')->get(),
        ]);
    }

    /**
     * Store a newly created session in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:competition_sessions,name'],
            'is_active' => ['nullable', 'boolean'],
            'r2_locked' => ['nullable', 'boolean'],
        ]);

        $session = CompetitionSession::create($validated);

        if ($session->is_active) {
            CompetitionSession::where('id', '!=', $session->id)->update(['is_active' => false]);
        }

        $msg = __('Session created successfully.');
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $msg]);
        } else {
            session()->flash('toast', ['type' => 'success', 'message' => $msg]);
        }

        return redirect()->route('admin.sessions.index');
    }

    /**
     * Update the specified session in storage.
     */
    public function update(Request $request, CompetitionSession $session): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('competition_sessions', 'name')->ignore($session->id),
            ],
            'is_active' => ['required', 'boolean'],
            'r2_locked' => ['required', 'boolean'],
        ]);

        $anotherActiveSessionExists = CompetitionSession::where('id', '!=', $session->id)
            ->where('is_active', true)
            ->exists();

        if ($session->is_active && !$validated['is_active'] && !$anotherActiveSessionExists) {
            return redirect()->route('admin.sessions.index')
                ->with('error', 'At least one competition session must remain active. Activate another session first.');
        }

        $session->update($validated);

        if ($session->is_active) {
            CompetitionSession::where('id', '!=', $session->id)->update(['is_active' => false]);
        }

        $msg = __('Session updated successfully.');
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $msg]);
        } else {
            session()->flash('toast', ['type' => 'success', 'message' => $msg]);
        }

        return redirect()->route('admin.sessions.index');
    }

    /**
     * Remove the specified session from storage.
     */
    public function destroy(CompetitionSession $session): RedirectResponse
    {
        if ($session->is_active) {
            $msg = __('Cannot delete the active session.');
            if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
                Inertia::flash('toast', ['type' => 'error', 'message' => $msg]);
            } else {
                session()->flash('toast', ['type' => 'error', 'message' => $msg]);
            }

            return redirect()->route('admin.sessions.index');
        }

        $session->delete();

        $msg = __('Session deleted successfully.');
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $msg]);
        } else {
            session()->flash('toast', ['type' => 'success', 'message' => $msg]);
        }

        return redirect()->route('admin.sessions.index');
    }
}
