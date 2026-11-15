<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompetitionSession;
use App\Services\CompetitionAnalytics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(Request $request, CompetitionAnalytics $analytics): Response
    {
        $request->validate(['session_id' => ['sometimes', 'integer', 'exists:competition_sessions,id']]);

        $sessions = CompetitionSession::orderByDesc('id')->get(['id', 'name', 'is_active']);
        $selectedId = $request->integer('session_id') ?: (CompetitionSession::active()->id ?? $sessions->first()?->id);
        $selected = $selectedId ? CompetitionSession::find($selectedId) : null;

        return Inertia::render('admin/analytics/Index', [
            'sessions' => $sessions,
            'selectedSessionId' => $selected?->id,
            'trend' => $analytics->trend(),
            'session' => $selected ? $analytics->forSession($selected) : null,
            'minSharedEvaluations' => CompetitionAnalytics::MIN_SHARED_EVALUATIONS,
        ]);
    }
}
