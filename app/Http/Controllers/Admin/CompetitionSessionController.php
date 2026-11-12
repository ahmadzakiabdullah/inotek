<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\Project;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        // Categories are shared across sessions, so count them once.
        $categoriesCount = Category::count();

        return Inertia::render('admin/sessions/index', [
            'sessions' => CompetitionSession::orderByDesc('id')->get()
                ->each(fn (CompetitionSession $session) => $session->setAttribute('categories_count', $categoriesCount)),
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
            'registration_mode' => ['required', Rule::in(['scheduled', 'open', 'closed'])],
            'session_starts_at' => ['required', 'date'],
            'session_ends_at' => ['required', 'date', 'after_or_equal:session_starts_at'],
            'registration_opens_at' => ['nullable', 'date', Rule::when($request->input('registration_mode') === 'scheduled', ['required'])],
            'registration_closes_at' => ['nullable', 'date', Rule::when($request->input('registration_mode') === 'scheduled', ['required']), Rule::when($request->filled('registration_opens_at'), ['after_or_equal:registration_opens_at'])],
            // r2_locked is intentionally not accepted here: results are finalized
            // only through Round2Controller::lockToggle, which writes an audit log.
        ]);

        DB::transaction(function () use ($validated): void {
            if ((bool) ($validated['is_active'] ?? false)) {
                CompetitionSession::query()->lockForUpdate()->get();
                CompetitionSession::query()->update(['is_active' => false]);
                $validated['is_active'] = true;
            }

            CompetitionSession::create($validated);
        });

        $msg = __('Session created successfully.');
        $this->toast(['type' => 'success', 'message' => $msg]);

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
            'registration_mode' => ['required', Rule::in(['scheduled', 'open', 'closed'])],
            'session_starts_at' => ['required', 'date'],
            'session_ends_at' => ['required', 'date', 'after_or_equal:session_starts_at'],
            'registration_opens_at' => ['nullable', 'date', Rule::when($request->input('registration_mode') === 'scheduled', ['required'])],
            'registration_closes_at' => ['nullable', 'date', Rule::when($request->input('registration_mode') === 'scheduled', ['required']), Rule::when($request->filled('registration_opens_at'), ['after_or_equal:registration_opens_at'])],
            // r2_locked is changed only through Round2Controller::lockToggle (audited).
        ]);

        DB::transaction(function () use ($session, $validated): void {
            CompetitionSession::query()->lockForUpdate()->get();

            if ($validated['is_active']) {
                CompetitionSession::where('id', '!=', $session->id)->update(['is_active' => false]);
            }

            $session->update($validated);
        });

        $msg = __('Session updated successfully.');
        $this->toast(['type' => 'success', 'message' => $msg]);

        return redirect()->route('admin.sessions.index');
    }

    /**
     * Remove the specified session from storage.
     */
    public function destroy(CompetitionSession $session): RedirectResponse
    {
        if ($session->is_active) {
            $msg = __('Cannot delete the active session.');
            $this->toast(['type' => 'error', 'message' => $msg]);

            return redirect()->route('admin.sessions.index');
        }

        // Projects, scores and assignments cascade on delete, so a session with
        // any project history must be kept for the record.
        if (Project::withTrashed()->where('session_id', $session->id)->exists()) {
            $this->toast(['type' => 'error', 'message' => __('This session has projects and cannot be deleted. Keep it as an inactive session instead.')]);

            return redirect()->route('admin.sessions.index');
        }

        $session->delete();

        AuditLogger::log('DELETE_SESSION', "Deleted competition session: '{$session->name}'");

        $msg = __('Session deleted successfully.');
        $this->toast(['type' => 'success', 'message' => $msg]);

        return redirect()->route('admin.sessions.index');
    }
}
