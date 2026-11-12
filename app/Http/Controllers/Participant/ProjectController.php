<?php

namespace App\Http\Controllers\Participant;

use App\Concerns\ProjectValidationRules;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\Project;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\ProjectCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    use ProjectValidationRules;

    /**
     * Display the participant's project dashboard/form.
     */
    public function index(): Response
    {
        $activeSession = CompetitionSession::active();

        $project = null;
        $categories = [];

        if ($activeSession) {
            $categories = Category::orderBy('code')->get();
            $project = Project::with('teamMembers', 'category')
                ->where('session_id', $activeSession->id)
                ->where('user_id', Auth::id())
                ->first();
        }

        return Inertia::render('participant/project/index', [
            'activeSession' => $activeSession,
            'registrationOpen' => (bool) ($activeSession?->isRegistrationOpen()),
            'categories' => $categories,
            'project' => $project,
        ]);
    }

    /**
     * Store a newly created project in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $activeSession = CompetitionSession::active();

        if (! $activeSession || ! $activeSession->isRegistrationOpen()) {
            return back()->withErrors(['session' => __('Registration is currently closed.')]);
        }

        // One project per participant per session (the route is limited to participants).
        $user = Auth::user();
        $existing = Project::where('session_id', $activeSession->id)
            ->where('user_id', $user->id)
            ->exists();
        if ($existing) {
            return back()->withErrors(['project' => __('You have already registered a project in this session.')]);
        }

        $validated = $request->validate($this->projectRules());
        $this->ensureTeamAllowed(Category::find((int) $validated['category_id']), $validated);

        DB::transaction(function () use ($validated, $activeSession, $user) {
            $project = Project::create([
                'session_id' => $activeSession->id,
                'category_id' => $validated['category_id'],
                'user_id' => $user->id,
                'status' => Project::STATUS_NEW,
                ...$this->projectAttributes($validated),
            ]);

            $this->syncTeamMembers($project, $validated);
        });

        $msg = __('Project registered as draft.');
        $this->toast(['type' => 'success', 'message' => $msg]);

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

        if (! $project->session?->isRegistrationOpen()) {
            return back()->withErrors(['session' => __('Registration for this session is closed.')]);
        }

        // Only allow edits on draft statuses
        if (! in_array($project->status, [Project::STATUS_NEW, Project::STATUS_EDIT])) {
            return back()->withErrors(['status' => __('Cannot update a submitted or approved project.')]);
        }

        $validated = $request->validate($this->projectRules());
        $this->ensureTeamAllowed(Category::find((int) $validated['category_id']), $validated);

        DB::transaction(function () use ($validated, $project) {
            $project->update([
                'category_id' => $validated['category_id'],
                ...$this->projectAttributes($validated),
            ]);

            $this->syncTeamMembers($project, $validated);
        });

        $msg = __('Project updated successfully.');
        $this->toast(['type' => 'success', 'message' => $msg]);

        return redirect()->route('projects.index');
    }

    /**
     * Submit the project to the Admin for verification.
     */
    public function submit(Project $project, ProjectCodeGenerator $codeGenerator): RedirectResponse
    {
        if ($project->user_id !== Auth::id()) {
            abort(403, __('Unauthorized action.'));
        }

        if (! $project->session?->isRegistrationOpen()) {
            return back()->withErrors(['session' => __('Registration for this session is closed.')]);
        }

        if (! in_array($project->status, [Project::STATUS_NEW, Project::STATUS_EDIT])) {
            return back()->withErrors(['status' => __('Only draft projects can be submitted.')]);
        }

        DB::transaction(function () use ($project, $codeGenerator) {
            // Generate Project Code (pcode) if not set
            if (! $project->pcode) {
                $project->pcode = $codeGenerator->next($project->session_id, $project->category_id);
            }

            $project->status = Project::STATUS_SUBMITTED;
            $project->save();
        });

        // Notify Admins
        try {
            $admins = User::withRole('admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new SystemNotification(
                    'New Project Approval Request',
                    "Project '{$project->title}' ({$project->pcode}) requires review.",
                    '/dashboard/approvals',
                    'info'
                ));
            }
        } catch (\Exception $e) {
            // Keep app resilient if queue/broadcasting is misconfigured
        }

        $msg = __('Project submitted successfully.');
        $this->toast(['type' => 'success', 'message' => $msg]);

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

        if (! $project->session?->isRegistrationOpen()) {
            return back()->withErrors(['session' => __('Registration for this session is closed.')]);
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
        $this->toast(['type' => 'success', 'message' => $msg]);

        return redirect()->route('projects.index');
    }
}
