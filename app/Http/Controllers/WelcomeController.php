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
        $session = CompetitionSession::active();

        if (! $session) {
            return Inertia::render('welcome', [
                'activeSession' => null,
                'registration' => ['isOpen' => false, 'mode' => null, 'opensAt' => null, 'closesAt' => null, 'sessionHasStarted' => false],
                'showcase' => $this->emptyShowcase(),
            ]);
        }

        $projects = Project::where('session_id', $session->id)
            ->where('status', Project::STATUS_APPROVED)
            ->withAvg(['scores as round_one_score' => fn ($query) => $query->where('round_no', 1)], 'total')
            ->withAvg(['scores as round_two_score' => fn ($query) => $query->where('round_no', 2)], 'total')
            ->get();

        // Rankings are confidential until the results are finalized. Round 2
        // finalists always rank above projects judged only in Round 1.
        $rankings = ! $session->r2_locked ? [] : $projects->sortBy([
            fn (Project $a, Project $b) => ($b->round_two_score !== null) <=> ($a->round_two_score !== null),
            fn (Project $a, Project $b) => (float) ($b->round_two_score ?? $b->round_one_score ?? 0)
                <=> (float) ($a->round_two_score ?? $a->round_one_score ?? 0),
        ])->take(3)->map(fn (Project $project) => [
            'name' => $project->title,
        ])->values()->all();

        $assignments = DB::table('judge_assignments')->where('session_id', $session->id)->count();
        $scores = DB::table('scores')->where('session_id', $session->id)->count();
        $sessionCategoryIds = Project::where('session_id', $session->id)
            ->where('status', Project::STATUS_APPROVED)
            ->select('category_id')
            ->distinct();
        $showcaseCategories = Category::query()
            ->whereIn('id', $sessionCategoryIds)
            ->orderBy('code')
            ->get(['code', 'name']);

        return Inertia::render('welcome', [
            'activeSession' => $session->name,
            'registration' => [
                'isOpen' => $session->isRegistrationOpen(),
                'mode' => $session->registration_mode,
                'opensAt' => $session->registration_opens_at?->format('Y-m-d'),
                'closesAt' => $session->registration_closes_at?->format('Y-m-d'),
                'sessionHasStarted' => ! $session->session_starts_at || now(config('app.timezone'))->startOfDay()->greaterThanOrEqualTo($session->session_starts_at),
            ],
            'showcase' => [
                'submissions' => $projects->count(),
                'categories' => $showcaseCategories->count(),
                'categoryList' => $showcaseCategories->map(fn (Category $category) => [
                    'code' => $category->code,
                    'name' => $category->name,
                ])->values()->all(),
                'progress' => $assignments > 0 ? min(100, round(($scores / $assignments) * 100)) : 0,
                'rankings' => $rankings,
            ],
        ]);
    }

    /** @return array{submissions: int, categories: int, categoryList: list<array{code: string, name: string}>, progress: int, rankings: list<array{name: string}>} */
    private function emptyShowcase(): array
    {
        return ['submissions' => 0, 'categories' => 0, 'categoryList' => [], 'progress' => 0, 'rankings' => []];
    }
}
