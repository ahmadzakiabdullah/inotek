<?php

namespace App\Http\Controllers;

use App\Models\CompetitionSession;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Handle the dashboard view.
     */
    public function index(): Response|RedirectResponse
    {
        $user = Auth::user();
        
        if ($user->hasRole('judge')) {
            return redirect()->route('judge.evaluations.index');
        }

        $activeSession = CompetitionSession::active();

        $data = [
            'activeSession' => $activeSession,
            'role' => $user->role?->name,
        ];

        if ($user->hasRole('admin')) {
            $sessionProjects = Project::query()
                ->when($activeSession, fn ($query) => $query->where('session_id', $activeSession->id))
                ->when(! $activeSession, fn ($query) => $query->whereRaw('1 = 0'));

            $data['stats'] = [
                'total_projects' => (clone $sessionProjects)->count(),
                'pending_reviews' => (clone $sessionProjects)->where('status', Project::STATUS_SUBMITTED)->count(),
                'approved_projects' => (clone $sessionProjects)->where('status', Project::STATUS_APPROVED)->count(),
                'rejected_projects' => (clone $sessionProjects)->where('status', Project::STATUS_EDIT)->count(),
            ];
            $data['recent_projects'] = (clone $sessionProjects)->with(['user', 'category'])
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            // Calculate Judging progress per category
            $categoryProgress = [];
            $pendingJudges = [];

            if ($activeSession) {
                $categories = \App\Models\Category::orderBy('code')->get();

                // Two grouped queries instead of three queries per category.
                $countByCategory = fn (string $table) => DB::table($table)
                    ->join('projects', 'projects.id', '=', "{$table}.project_id")
                    ->where("{$table}.session_id", $activeSession->id)
                    ->where('projects.session_id', $activeSession->id)
                    ->where('projects.status', Project::STATUS_APPROVED)
                    ->whereNull('projects.deleted_at')
                    ->groupBy('projects.category_id')
                    ->pluck(DB::raw('COUNT(*)'), 'projects.category_id');
                $assignmentCounts = $countByCategory('judge_assignments');
                $scoreCounts = $countByCategory('scores');

                foreach ($categories as $cat) {
                    $totalAssignments = (int) ($assignmentCounts[$cat->id] ?? 0);
                    $completedScores = (int) ($scoreCounts[$cat->id] ?? 0);

                    $percentage = $totalAssignments > 0 ? round(($completedScores / $totalAssignments) * 100, 1) : 100.0;

                    $categoryProgress[] = [
                        'id' => $cat->id,
                        'code' => $cat->code,
                        'name' => $cat->name,
                        'total_assignments' => $totalAssignments,
                        'completed_scores' => $completedScores,
                        'progress_percentage' => $percentage,
                    ];
                }

                // Calculate pending judges and their projects
                $assignments = \App\Models\JudgeAssignment::with(['project.category', 'judge'])
                    ->where('session_id', $activeSession->id)
                    ->get();

                $scores = \App\Models\Score::where('session_id', $activeSession->id)
                    ->get()
                    ->groupBy(function ($score) {
                        return $score->project_id . '-' . $score->judge_id . '-' . $score->round_no;
                    });

                $pendingGrouped = [];
                foreach ($assignments as $assignment) {
                    $key = $assignment->project_id . '-' . $assignment->judge_id . '-' . $assignment->round_no;
                    if (!$scores->has($key)) {
                        $judgeId = $assignment->judge_id;
                        if (!isset($pendingGrouped[$judgeId])) {
                            $pendingGrouped[$judgeId] = [
                                'id' => $judgeId,
                                'name' => $assignment->judge->name ?? 'Unknown Judge',
                                'email' => $assignment->judge->email ?? '',
                                'pending_projects' => collect(),
                            ];
                        }
                        $pendingGrouped[$judgeId]['pending_projects']->push([
                            'pcode' => $assignment->project->pcode,
                            'title' => $assignment->project->title,
                            'category_name' => $assignment->project->category->name ?? 'N/A',
                            'round_no' => $assignment->round_no,
                        ]);
                    }
                }

                foreach ($pendingGrouped as $dataGroup) {
                    $dataGroup['pending_projects'] = $dataGroup['pending_projects']->values()->all();
                    $pendingJudges[] = $dataGroup;
                }
            }

            $data['categoryProgress'] = $categoryProgress;
            $data['pendingJudges'] = $pendingJudges;
        } elseif ($user->hasRole('lecturer')) {
            $myProjects = Project::with(['user', 'category'])
                ->whereRaw('LOWER(supervisor_email) = ?', [Str::lower($user->email)])
                ->when($activeSession, fn ($query) => $query->where('session_id', $activeSession->id))
                ->when(! $activeSession, fn ($query) => $query->whereRaw('1 = 0'))
                ->orderByDesc('id')
                ->get();

            $data['stats'] = [
                'total_projects' => $myProjects->count(),
                'pending_reviews' => $myProjects->where('status', Project::STATUS_SUBMITTED)->count(),
                'approved_projects' => $myProjects->where('status', Project::STATUS_APPROVED)->count(),
                'rejected_projects' => $myProjects->where('status', Project::STATUS_EDIT)->count(),
            ];
            $data['my_projects'] = $myProjects->each(fn (Project $project) => $project->hideUnpublishedAward($activeSession));
        } else {
            // Student / User role
            $project = null;
            if ($activeSession) {
                $project = Project::with(['category', 'teamMembers'])
                    ->where('session_id', $activeSession->id)
                    ->where('user_id', $user->id)
                    ->first()
                    ?->hideUnpublishedAward($activeSession);
            }
            $data['project'] = $project;
        }

        return Inertia::render('dashboard', $data);
    }
}
