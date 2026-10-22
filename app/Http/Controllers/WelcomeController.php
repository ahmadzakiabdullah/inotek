<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    public function __invoke(): Response
    {
        $session = CompetitionSession::where('is_active', true)->first();

        if (! $session) {
            return Inertia::render('welcome', [
                'showcase' => $this->emptyShowcase(),
            ]);
        }

        $projects = Project::where('session_id', $session->id)
            ->where('status', Project::STATUS_APPROVED)
            ->with('user')
            ->withAvg(['scores as round_one_score' => fn ($query) => $query->where('round_no', 1)], 'total')
            ->withAvg(['scores as round_two_score' => fn ($query) => $query->where('round_no', 2)], 'total')
            ->get();

        $rankings = $projects->sortByDesc(fn (Project $project) => $project->round_two_score ?: $project->round_one_score ?: 0
        )->take(3)->map(fn (Project $project) => [
            'name' => $project->title,
        ])->values()->all();

        $assignments = DB::table('judge_assignments')->where('session_id', $session->id)->count();
        $scores = DB::table('scores')->where('session_id', $session->id)->count();

        return Inertia::render('welcome', [
            'showcase' => [
                'submissions' => $projects->count(),
                'categories' => Category::where('session_id', $session->id)->count(),
                'progress' => $assignments > 0 ? min(100, round(($scores / $assignments) * 100)) : 0,
                'rankings' => $rankings,
            ],
        ]);
    }

    /** @return array{submissions: int, categories: int, progress: int, rankings: list<array{name: string}>} */
    private function emptyShowcase(): array
    {
        return ['submissions' => 0, 'categories' => 0, 'progress' => 0, 'rankings' => []];
    }
}
