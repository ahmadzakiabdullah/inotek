<?php

namespace App\Http\Controllers;

use App\Models\CompetitionSession;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
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

        $activeSession = CompetitionSession::where('is_active', true)->first();

        $data = [
            'activeSession' => $activeSession,
            'role' => $user->role?->name,
        ];

        if ($user->hasRole('admin')) {
            $data['stats'] = [
                'total_projects' => Project::count(),
                'pending_reviews' => Project::where('status', Project::STATUS_SUBMITTED)->count(),
                'approved_projects' => Project::where('status', Project::STATUS_APPROVED)->count(),
                'rejected_projects' => Project::where('status', Project::STATUS_EDIT)->count(),
            ];
            $data['recent_projects'] = Project::with(['user', 'category'])
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            // Calculate Judging progress per category
            $categoryProgress = [];
            $pendingJudges = [];

            if ($activeSession) {
                $categories = \App\Models\Category::where('session_id', $activeSession->id)->get();
                foreach ($categories as $cat) {
                    $approvedProjectIds = Project::where('session_id', $activeSession->id)
                        ->where('category_id', $cat->id)
                        ->where('status', Project::STATUS_APPROVED)
                        ->pluck('id');

                    $totalAssignments = \App\Models\JudgeAssignment::whereIn('project_id', $approvedProjectIds)
                        ->where('session_id', $activeSession->id)
                        ->count();

                    $completedScores = \App\Models\Score::whereIn('project_id', $approvedProjectIds)
                        ->where('session_id', $activeSession->id)
                        ->count();

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
                ->where('supervisor_email', $user->email)
                ->orderByDesc('id')
                ->get();

            $data['stats'] = [
                'total_projects' => $myProjects->count(),
                'pending_reviews' => $myProjects->where('status', Project::STATUS_SUBMITTED)->count(),
                'approved_projects' => $myProjects->where('status', Project::STATUS_APPROVED)->count(),
                'rejected_projects' => $myProjects->where('status', Project::STATUS_EDIT)->count(),
            ];
            $data['my_projects'] = $myProjects;
        } else {
            // Student / User role
            $project = null;
            if ($activeSession) {
                $project = Project::with(['category', 'teamMembers'])
                    ->where('session_id', $activeSession->id)
                    ->where('user_id', $user->id)
                    ->first();
            }
            $data['project'] = $project;
        }

        return Inertia::render('dashboard', $data);
    }
}
