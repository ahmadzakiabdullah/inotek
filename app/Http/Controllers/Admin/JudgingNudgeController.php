<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompetitionSession;
use App\Models\JudgeAssignment;
use App\Models\Score;
use App\Models\User;
use App\Mail\JudgeNudgeMail;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class JudgingNudgeController extends Controller
{
    /**
     * Send email reminders (nudges) to all judges with pending evaluations.
     */
    public function nudge(Request $request): RedirectResponse
    {
        $activeSession = CompetitionSession::where('is_active', true)->first();

        if (!$activeSession) {
            return back()->withErrors(['session' => 'No active competition session found.']);
        }

        // Get all judge assignments for the active session
        $assignments = JudgeAssignment::with(['project.category', 'judge'])
            ->where('session_id', $activeSession->id)
            ->get();

        // Get all scores submitted for the active session
        $scores = Score::where('session_id', $activeSession->id)
            ->get()
            ->groupBy(function ($score) {
                return $score->project_id . '-' . $score->judge_id . '-' . $score->round_no;
            });

        // Group pending evaluations by judge
        $pendingGrouped = [];

        foreach ($assignments as $assignment) {
            $key = $assignment->project_id . '-' . $assignment->judge_id . '-' . $assignment->round_no;
            if (!$scores->has($key)) {
                $judgeId = $assignment->judge_id;
                if (!isset($pendingGrouped[$judgeId])) {
                    $pendingGrouped[$judgeId] = [
                        'user' => $assignment->judge,
                        'projects' => collect(),
                    ];
                }

                $pendingGrouped[$judgeId]['projects']->push([
                    'pcode' => $assignment->project->pcode,
                    'title' => $assignment->project->title,
                    'category_name' => $assignment->project->category->name ?? 'N/A',
                    'round_no' => $assignment->round_no,
                ]);
            }
        }

        $nudgeCount = 0;

        foreach ($pendingGrouped as $pendingData) {
            $judge = $pendingData['user'];
            $projects = $pendingData['projects'];

            if ($judge && $judge->email) {
                Mail::to($judge->email)->send(new JudgeNudgeMail($judge, $projects));
                $nudgeCount++;
            }
        }

        if ($nudgeCount > 0) {
            AuditLogger::log(
                'NUDGE_JUDGES',
                "Sent email evaluation reminders to {$nudgeCount} judges with pending appraisals."
            );
        }

        $msg = __("Successfully sent evaluation reminders to {$nudgeCount} pending judges.");
        
        return back()->with('success', $msg);
    }
}
