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
        ]);
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
            $query = Project::whereIn('id', $validated['project_ids']);
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
                                '/projects',
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
                    '/projects',
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
        $query = Project::whereIn('id', $validated['project_ids']);
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
