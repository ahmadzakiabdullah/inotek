<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectApprovalController extends Controller
{
    /**
     * Display a listing of projects for admin review.
     */
    public function index(): Response
    {
        $user = auth()->user();
        $query = Project::with(['user', 'category', 'session', 'teamMembers'])->orderByDesc('id');

        if ($user->hasRole('lecturer')) {
            $query->where('supervisor_email', $user->email);
        }

        return Inertia::render('admin/approvals/index', [
            'projects' => $query->get(),
            'sessions' => CompetitionSession::orderByDesc('id')->get(),
            'categories' => Category::orderBy('name')->get(['id', 'name', 'session_id']),
            'students' => \App\Models\User::where('role_id', 4)->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    /**
     * Store a new project registered by Admin.
     */
    public function storeProject(Request $request): RedirectResponse
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized action.');
        }

        $activeSession = CompetitionSession::where('is_active', true)->first();
        if (!$activeSession) {
            return back()->with('error', 'No active competition session found.');
        }

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'category_id' => ['required', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'abstract' => ['required', 'string'],
            'institution_type' => ['required', 'string', 'in:utem,ipt'],
            'supervisor_name' => ['required', 'string', 'max:255'],
            'supervisor_email' => ['required', 'email', 'max:255'],
            'supervisor_phone' => ['nullable', 'string', 'max:100'],
            'video_url' => ['nullable', 'url', 'max:255'],
            'poster_url' => ['nullable', 'url', 'max:255'],
            'team_members' => ['nullable', 'array'],
            'team_members.*.name' => ['required', 'string', 'max:255'],
            'team_members.*.email' => ['nullable', 'email', 'max:255'],
            'team_members.*.phone' => ['nullable', 'string', 'max:100'],
        ]);

        // Verify the student does not already have a project in the active session
        $existing = Project::where('session_id', $activeSession->id)
            ->where('user_id', $validated['user_id'])
            ->exists();
        if ($existing) {
            $msg = __('This student already has a project registered in the active session.');
            if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
                Inertia::flash('toast', ['type' => 'error', 'message' => $msg]);
            } else {
                session()->flash('toast', ['type' => 'error', 'message' => $msg]);
            }
            return back()->withErrors(['user_id' => $msg]);
        }

        DB::transaction(function () use ($validated, $activeSession) {
            $category = Category::findOrFail($validated['category_id']);
            
            // Auto-generate project code (pcode)
            $count = Project::where('session_id', $activeSession->id)
                ->where('category_id', $validated['category_id'])
                ->whereNotNull('pcode')
                ->count();
            $nextNum = str_pad($count + 1, 2, '0', STR_PAD_LEFT);
            $pcode = "{$category->code}-{$nextNum}";

            $project = Project::create([
                'session_id' => $activeSession->id,
                'category_id' => $validated['category_id'],
                'user_id' => $validated['user_id'],
                'pcode' => $pcode,
                'title' => $validated['title'],
                'abstract' => $validated['abstract'],
                'institution_type' => $validated['institution_type'],
                'status' => Project::STATUS_APPROVED, // Direct Approval
                'supervisor_name' => $validated['supervisor_name'],
                'supervisor_email' => $validated['supervisor_email'],
                'supervisor_phone' => $validated['supervisor_phone'] ?? null,
                'video_url' => $validated['video_url'] ?? null,
                'poster_url' => $validated['poster_url'] ?? null,
            ]);

            if (!empty($validated['team_members'])) {
                foreach ($validated['team_members'] as $member) {
                    \App\Models\TeamMember::create([
                        'project_id' => $project->id,
                        'name' => $member['name'],
                        'email' => $member['email'] ?? null,
                        'phone' => $member['phone'] ?? null,
                    ]);
                }
            }

            \App\Services\AuditLogger::log(
                'CREATE_PROJECT_ADMIN',
                "Admin registered project: '{$project->title}' (Code: {$project->pcode}) for student ID: {$project->user_id}"
            );
        });

        $msg = __('Project created and approved successfully.');
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $msg]);
        } else {
            session()->flash('toast', ['type' => 'success', 'message' => $msg]);
        }

        return redirect()->route('admin.approvals.index');
    }

    /**
     * Bulk approve the selected projects.
     */
    public function approve(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_ids' => ['required', 'array', 'min:1'],
            'project_ids.*' => ['exists:projects,id'],
        ]);

        $user = auth()->user();

        DB::transaction(function () use ($validated, $user) {
            $query = Project::whereIn('id', $validated['project_ids'])
                ->where('status', Project::STATUS_SUBMITTED);
            if ($user->hasRole('lecturer')) {
                $query->where('supervisor_email', $user->email);
            }
            $projects = $query->get();

            foreach ($projects as $project) {
                if ($project->status !== Project::STATUS_APPROVED) {
                    // Auto-generate project code (pcode) if not set
                    if (! $project->pcode) {
                        $category = Category::findOrFail($project->category_id);
                        $count = Project::where('session_id', $project->session_id)
                            ->where('category_id', $project->category_id)
                            ->whereNotNull('pcode')
                            ->count();

                        $nextNum = str_pad($count + 1, 2, '0', STR_PAD_LEFT);
                        $project->pcode = "{$category->code}-{$nextNum}";
                    }

                    $project->status = Project::STATUS_APPROVED;
                    $project->admin_comments = null; // Clear comments upon approval
                    $project->save();

                    \App\Services\AuditLogger::log(
                        'APPROVE_PROJECT',
                        "Approved project: '{$project->title}' (Code: {$project->pcode})"
                    );

                    // Notify Student
                    try {
                        if ($project->user) {
                            $project->user->notify(new \App\Notifications\SystemNotification(
                                'Project Approved',
                                "Your project '{$project->title}' ({$project->pcode}) has been approved.",
                                '/dashboard/projects',
                                'success'
                            ));
                        }
                    } catch (\Exception $e) {
                        // Keep resilient
                    }
                }
            }
        });

        $msg = __('Selected projects approved successfully.');
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $msg]);
        } else {
            session()->flash('toast', ['type' => 'success', 'message' => $msg]);
        }

        return redirect()->route('admin.approvals.index');
    }

    /**
     * Reject a single project and request changes.
     */
    public function reject(Request $request, Project $project): RedirectResponse
    {
        $user = auth()->user();
        if ($user->hasRole('lecturer') && $project->supervisor_email !== $user->email) {
            abort(403, __('Unauthorized action.'));
        }

        $validated = $request->validate([
            'admin_comments' => ['required', 'string', 'max:1000'],
        ]);

        if ($project->status !== Project::STATUS_SUBMITTED) {
            abort(403, __('Only submitted projects can be rejected.'));
        }

        $project->update([
            'status' => Project::STATUS_EDIT,
            'admin_comments' => $validated['admin_comments'],
        ]);

        \App\Services\AuditLogger::log(
            'REJECT_PROJECT',
            "Rejected project: '{$project->title}' (Reason: {$validated['admin_comments']})"
        );

        // Notify Student
        try {
            if ($project->user) {
                $project->user->notify(new \App\Notifications\SystemNotification(
                    'Project Returned for Editing',
                    "Your project '{$project->title}' has been returned for correction. Reason: {$validated['admin_comments']}",
                    '/dashboard/projects',
                    'warning'
                ));
            }
        } catch (\Exception $e) {
            // Keep resilient
        }

        $msg = __('Project rejected and sent back for editing.');
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => $msg]);
        } else {
            session()->flash('toast', ['type' => 'warning', 'message' => $msg]);
        }

        return redirect()->route('admin.approvals.index');
    }

    /**
     * Bulk cancel/reject the selected projects.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_ids' => ['required', 'array', 'min:1'],
            'project_ids.*' => ['exists:projects,id'],
        ]);

        $user = auth()->user();
        $query = Project::whereIn('id', $validated['project_ids'])
            ->where('status', Project::STATUS_SUBMITTED);
        if ($user->hasRole('lecturer')) {
            $query->where('supervisor_email', $user->email);
        }

        $projects = $query->get();
        $query->update([
            'status' => Project::STATUS_CANCELLED,
        ]);

        foreach ($projects as $project) {
            \App\Services\AuditLogger::log(
                'CANCEL_PROJECT',
                "Cancelled project: '{$project->title}'"
            );
        }

        $msg = __('Selected projects cancelled successfully.');
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => $msg]);
        } else {
            session()->flash('toast', ['type' => 'warning', 'message' => $msg]);
        }

        return redirect()->route('admin.approvals.index');
    }

    /**
     * Manually override or update the project code.
     */
    public function updateCode(Request $request, Project $project): RedirectResponse
    {
        if ($project->status === Project::STATUS_NEW) {
            abort(403, __('New projects are view-only until submitted.'));
        }

        $user = auth()->user();
        if ($user->hasRole('lecturer') && $project->supervisor_email !== $user->email) {
            abort(403, __('Unauthorized action.'));
        }

        $validated = $request->validate([
            'pcode' => [
                'required',
                'string',
                'max:100',
                Rule::unique('projects', 'pcode')
                    ->ignore($project->id)
                    ->where('session_id', $project->session_id),
            ],
        ]);

        $oldCode = $project->pcode;
        $project->update([
            'pcode' => $validated['pcode'],
        ]);

        \App\Services\AuditLogger::log(
            'UPDATE_PROJECT_CODE',
            "Updated project code for '{$project->title}' from '{$oldCode}' to '{$validated['pcode']}'"
        );

        $msg = __('Project code updated successfully.');
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $msg]);
        } else {
            session()->flash('toast', ['type' => 'success', 'message' => $msg]);
        }

        return redirect()->route('admin.approvals.index');
    }
}
