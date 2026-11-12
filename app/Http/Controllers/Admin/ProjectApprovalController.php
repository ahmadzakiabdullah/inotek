<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\ProjectValidationRules;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\Project;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\AuditLogger;
use App\Services\ProjectCodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectApprovalController extends Controller
{
    use ProjectValidationRules;

    /**
     * Display a listing of projects for admin review.
     */
    public function index(): Response
    {
        $user = auth()->user();
        $query = Project::with(['user', 'category', 'session', 'teamMembers'])->orderByDesc('id');
        $this->limitToReviewer($query, $user);

        return Inertia::render('admin/approvals/index', [
            'projects' => $user->hasRole('admin')
                ? $query->get()
                : $query->get()->each(fn (Project $project) => $project->hideUnpublishedAward()),
            'sessions' => CompetitionSession::orderByDesc('id')->get(),
            'categories' => Category::orderBy('code')->get(['id', 'name', 'code']),
            // Only admins can register projects for students, so only they need the student list.
            'students' => $user->hasRole('admin')
                ? User::withRole('user')->orderBy('name')->get(['id', 'name', 'email'])
                : [],
        ]);
    }

    /**
     * Soft-delete a project from the admin approvals list.
     */
    public function destroy(Project $project): RedirectResponse
    {
        $user = auth()->user();
        if (! $user?->hasRole('admin')) {
            abort(403, 'Unauthorized action.');
        }

        DB::transaction(function () use ($project): void {
            $project->delete();

            AuditLogger::log(
                'DELETE_PROJECT',
                "Admin soft-deleted project '{$project->title}' (Code: {$project->pcode})."
            );
        });

        $this->toast(['type' => 'success', 'message' => __('Project deleted successfully.')]);

        return redirect()->route('admin.approvals.index');
    }

    /**
     * Update project information without changing its session or category.
     */
    public function updateProject(Request $request, Project $project): RedirectResponse
    {
        $user = auth()->user();
        if (! $user?->hasRole('admin')) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate($this->projectRules(withCategory: false));
        $this->ensureTeamAllowed($project->category, $validated);

        DB::transaction(function () use ($project, $validated): void {
            $oldTitle = $project->title;
            $project->update($this->projectAttributes($validated));
            $this->syncTeamMembers($project, $validated);

            AuditLogger::log(
                'UPDATE_PROJECT_ADMIN',
                "Admin updated project '{$oldTitle}' (Code: {$project->pcode})."
            );
        });

        $this->toast(['type' => 'success', 'message' => __('Project updated successfully.')]);

        return redirect()->route('admin.approvals.index');
    }

    /**
     * Store a new project registered by Admin.
     */
    public function storeProject(Request $request, ProjectCodeGenerator $codeGenerator): RedirectResponse
    {
        if (! auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized action.');
        }

        $activeSession = CompetitionSession::active();
        if (! $activeSession) {
            return back()->with('error', 'No active competition session found.');
        }

        $validated = $request->validate([
            // Projects may only be registered for participant accounts.
            'user_id' => ['required', Rule::exists('users', 'id')->where(
                fn ($query) => $query->whereNull('deleted_at')
                    ->whereIn('role_id', fn ($roles) => $roles->select('id')->from('roles')->where('name', 'user'))
            )],
            ...$this->projectRules(),
        ], [
            'user_id.exists' => __('The selected account is not a participant.'),
        ]);

        // Verify the student does not already have a project in the active session
        $existing = Project::where('session_id', $activeSession->id)
            ->where('user_id', $validated['user_id'])
            ->exists();
        if ($existing) {
            $msg = __('This student already has a project registered in the active session.');
            $this->toast(['type' => 'error', 'message' => $msg]);

            return back()->withErrors(['user_id' => $msg]);
        }

        $category = Category::findOrFail((int) $validated['category_id']);
        $this->ensureTeamAllowed($category, $validated);

        DB::transaction(function () use ($validated, $activeSession, $codeGenerator, $category) {
            $project = Project::create([
                'session_id' => $activeSession->id,
                'category_id' => $category->id,
                'user_id' => $validated['user_id'],
                'pcode' => $codeGenerator->next($activeSession->id, $category->id),
                'status' => Project::STATUS_APPROVED, // Direct Approval
                ...$this->projectAttributes($validated),
            ]);

            $this->syncTeamMembers($project, $validated);

            AuditLogger::log(
                'CREATE_PROJECT_ADMIN',
                "Admin registered project: '{$project->title}' (Code: {$project->pcode}) for student ID: {$project->user_id}"
            );
        });

        $this->toast(['type' => 'success', 'message' => __('Project created and approved successfully.')]);

        return redirect()->route('admin.approvals.index');
    }

    /**
     * Bulk approve the selected projects.
     */
    public function approve(Request $request, ProjectCodeGenerator $codeGenerator): RedirectResponse
    {
        $validated = $request->validate([
            'project_ids' => ['required', 'array', 'min:1'],
            'project_ids.*' => ['exists:projects,id'],
        ]);

        $user = auth()->user();

        DB::transaction(function () use ($validated, $user, $codeGenerator) {
            $query = Project::whereIn('id', $validated['project_ids'])
                ->where('status', Project::STATUS_SUBMITTED);
            $this->limitToReviewer($query, $user);

            foreach ($query->get() as $project) {
                // Auto-generate project code (pcode) if not set
                if (! $project->pcode) {
                    $project->pcode = $codeGenerator->next($project->session_id, $project->category_id);
                }

                $project->status = Project::STATUS_APPROVED;
                $project->admin_comments = null; // Clear comments upon approval
                $project->save();

                AuditLogger::log(
                    'APPROVE_PROJECT',
                    "Approved project: '{$project->title}' (Code: {$project->pcode})"
                );

                // Notify Student
                try {
                    $project->user?->notify(new SystemNotification(
                        'Project Approved',
                        "Your project '{$project->title}' ({$project->pcode}) has been approved.",
                        '/dashboard/projects',
                        'success'
                    ));
                } catch (\Exception $e) {
                    report($e);
                }
            }
        });

        $this->toast(['type' => 'success', 'message' => __('Selected projects approved successfully.')]);

        return redirect()->route('admin.approvals.index');
    }

    /**
     * Reject a single project and request changes.
     */
    public function reject(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeReview($project);

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

        AuditLogger::log(
            'REJECT_PROJECT',
            "Rejected project: '{$project->title}' (Reason: {$validated['admin_comments']})"
        );

        // Notify Student
        try {
            $project->user?->notify(new SystemNotification(
                'Project Returned for Editing',
                "Your project '{$project->title}' has been returned for correction. Reason: {$validated['admin_comments']}",
                '/dashboard/projects',
                'warning'
            ));
        } catch (\Exception $e) {
            report($e);
        }

        $this->toast(['type' => 'warning', 'message' => __('Project rejected and sent back for editing.')]);

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

        $query = Project::whereIn('id', $validated['project_ids'])
            ->where('status', Project::STATUS_SUBMITTED);
        $this->limitToReviewer($query, auth()->user());

        $projects = $query->get();
        Project::whereKey($projects->modelKeys())->update([
            'status' => Project::STATUS_CANCELLED,
        ]);

        foreach ($projects as $project) {
            AuditLogger::log(
                'CANCEL_PROJECT',
                "Cancelled project: '{$project->title}'"
            );
        }

        $this->toast(['type' => 'warning', 'message' => __('Selected projects cancelled successfully.')]);

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

        $this->authorizeReview($project);

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

        AuditLogger::log(
            'UPDATE_PROJECT_CODE',
            "Updated project code for '{$project->title}' from '{$oldCode}' to '{$validated['pcode']}'"
        );

        $this->toast(['type' => 'success', 'message' => __('Project code updated successfully.')]);

        return redirect()->route('admin.approvals.index');
    }

    /**
     * Lecturers only see projects they supervise, and nobody reviews their own project.
     *
     * @param  Builder<Project>  $query
     */
    private function limitToReviewer(Builder $query, User $user): void
    {
        if ($user->hasRole('lecturer')) {
            $query->whereRaw('LOWER(supervisor_email) = ?', [Str::lower($user->email)]);
        }

        $query->where(fn (Builder $owner) => $owner->whereNull('user_id')->orWhere('user_id', '!=', $user->id));
    }

    private function authorizeReview(Project $project): void
    {
        $user = auth()->user();

        $notSupervisor = $user->hasRole('lecturer')
            && strcasecmp((string) $project->supervisor_email, (string) $user->email) !== 0;

        if ($notSupervisor || $project->user_id === $user->id) {
            abort(403, __('Unauthorized action.'));
        }
    }
}
