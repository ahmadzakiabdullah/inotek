<?php

namespace App\Http\Controllers\Participant;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\Project;
use App\Models\TeamMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Display the participant's project dashboard/form.
     */
    public function index(): Response
    {
        $activeSession = CompetitionSession::where('is_active', true)->first();

        $project = null;
        $categories = [];

        if ($activeSession) {
            $categories = Category::where('session_id', $activeSession->id)->get();
            $project = Project::with('teamMembers', 'category')
                ->where('session_id', $activeSession->id)
                ->where('user_id', Auth::id())
                ->first();
        }

        return Inertia::render('participant/project/index', [
            'activeSession' => $activeSession,
            'categories' => $categories,
            'project' => $project,
        ]);
    }

    /**
     * Store a newly created project in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $activeSession = CompetitionSession::where('is_active', true)->first();

        if (! $activeSession) {
            return back()->withErrors(['session' => __('No active competition session found.')]);
        }

        // Limit students to 1 project per active session
        $user = Auth::user();
        if ($user->role?->name === 'user') {
            $existing = Project::where('session_id', $activeSession->id)
                ->where('user_id', $user->id)
                ->exists();
            if ($existing) {
                return back()->withErrors(['project' => __('You have already registered a project in this session.')]);
            }
        }

        $validated = $request->validate([
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

        $category = Category::findOrFail($validated['category_id']);
        if (! $category->allow_team && ! empty($validated['team_members'])) {
            return back()->withErrors(['team_members' => __('Team members are not allowed for this category.')]);
        }

        DB::transaction(function () use ($validated, $activeSession, $user) {
            $project = Project::create([
                'session_id' => $activeSession->id,
                'category_id' => $validated['category_id'],
                'user_id' => $user->id,
                'title' => $validated['title'],
                'abstract' => $validated['abstract'],
                'institution_type' => $validated['institution_type'],
                'status' => Project::STATUS_NEW,
                'supervisor_name' => $validated['supervisor_name'],
                'supervisor_email' => $validated['supervisor_email'],
                'supervisor_phone' => $validated['supervisor_phone'] ?? null,
                'video_url' => $validated['video_url'] ?? null,
                'poster_url' => $validated['poster_url'] ?? null,
            ]);

            if (! empty($validated['team_members'])) {
                foreach ($validated['team_members'] as $member) {
                    TeamMember::create([
                        'project_id' => $project->id,
                        'name' => $member['name'],
                        'email' => $member['email'] ?? null,
                        'phone' => $member['phone'] ?? null,
                    ]);
                }
            }
        });

        $msg = __('Project registered as draft.');
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $msg]);
        } else {
            session()->flash('toast', ['type' => 'success', 'message' => $msg]);
        }

        return redirect()->route('projects.index');
    }

    /**
     * Update the specified project in storage.
     */
    public function update(Request $request, Project $project): RedirectResponse
    {
        // Enforce ownership
        if ($project->user_id !== Auth::id()) {
            abort(403, __('Unauthorized action.'));
        }

        // Only allow edits on draft statuses
        if (! in_array($project->status, [Project::STATUS_NEW, Project::STATUS_EDIT])) {
            return back()->withErrors(['status' => __('Cannot update a submitted or approved project.')]);
        }

        $validated = $request->validate([
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

        $category = Category::findOrFail($validated['category_id']);
        if (! $category->allow_team && ! empty($validated['team_members'])) {
            return back()->withErrors(['team_members' => __('Team members are not allowed for this category.')]);
        }

        DB::transaction(function () use ($validated, $project) {
            $project->update([
                'category_id' => $validated['category_id'],
                'title' => $validated['title'],
                'abstract' => $validated['abstract'],
                'institution_type' => $validated['institution_type'],
                'supervisor_name' => $validated['supervisor_name'],
                'supervisor_email' => $validated['supervisor_email'],
                'supervisor_phone' => $validated['supervisor_phone'] ?? null,
                'video_url' => $validated['video_url'] ?? null,
                'poster_url' => $validated['poster_url'] ?? null,
            ]);

            // Sync team members (delete old, insert new)
            $project->teamMembers()->delete();
            if (! empty($validated['team_members'])) {
                foreach ($validated['team_members'] as $member) {
                    TeamMember::create([
                        'project_id' => $project->id,
                        'name' => $member['name'],
                        'email' => $member['email'] ?? null,
                        'phone' => $member['phone'] ?? null,
                    ]);
                }
            }
        });

        $msg = __('Project updated successfully.');
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $msg]);
        } else {
            session()->flash('toast', ['type' => 'success', 'message' => $msg]);
        }

        return redirect()->route('projects.index');
    }

    /**
     * Submit the project to the Admin for verification.
     */
    public function submit(Project $project): RedirectResponse
    {
        if ($project->user_id !== Auth::id()) {
            abort(403, __('Unauthorized action.'));
        }

        if (! in_array($project->status, [Project::STATUS_NEW, Project::STATUS_EDIT])) {
            return back()->withErrors(['status' => __('Only draft projects can be submitted.')]);
        }

        DB::transaction(function () use ($project) {
            // Generate Project Code (pcode) if not set
            if (! $project->pcode) {
                $category = Category::findOrFail($project->category_id);

                // Count projects in this category & session that have a project code assigned
                $count = Project::where('session_id', $project->session_id)
                    ->where('category_id', $project->category_id)
                    ->whereNotNull('pcode')
                    ->count();

                $nextNum = str_pad($count + 1, 2, '0', STR_PAD_LEFT);
                $project->pcode = "{$category->code}-{$nextNum}";
            }

            $project->status = Project::STATUS_SUBMITTED;
            $project->save();
        });

        // Notify Admins
        try {
            $admins = \App\Models\User::where('role_id', 1)->get();
            foreach ($admins as $admin) {
                $admin->notify(new \App\Notifications\SystemNotification(
                    'New Project Approval Request',
                    "Project '{$project->title}' ({$project->pcode}) requires review.",
                    '/admin/approvals',
                    'info'
                ));
            }
        } catch (\Exception $e) {
            // Keep app resilient if queue/broadcasting is misconfigured
        }

        $msg = __('Project submitted successfully.');
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $msg]);
        } else {
            session()->flash('toast', ['type' => 'success', 'message' => $msg]);
        }

        return redirect()->route('projects.index');
    }

    /**
     * Remove the specified project from storage.
     */
    public function destroy(Project $project): RedirectResponse
    {
        if ($project->user_id !== Auth::id()) {
            abort(403, __('Unauthorized action.'));
        }

        if (! in_array($project->status, [Project::STATUS_NEW, Project::STATUS_EDIT])) {
            return back()->withErrors(['status' => __('Cannot delete a submitted or approved project.')]);
        }

        DB::transaction(function () use ($project) {
            if ($project->poster_url) {
                $oldPath = str_replace('/storage/', '', $project->poster_url);
                Storage::disk('public')->delete($oldPath);
            }
            $project->teamMembers()->delete();
            $project->delete();
        });

        $msg = __('Project deleted successfully.');
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $msg]);
        } else {
            session()->flash('toast', ['type' => 'success', 'message' => $msg]);
        }

        return redirect()->route('projects.index');
    }
}
