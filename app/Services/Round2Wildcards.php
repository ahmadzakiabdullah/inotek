<?php

namespace App\Services;

use App\Models\CompetitionSession;
use App\Models\JudgeAssignment;
use App\Models\Project;
use App\Models\Round2Wildcard;
use App\Models\Score;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Round 2 wildcards: projects an admin adds to Round 2 on top of the automatic
 * Round 1 shortlist, chosen from Best Presenter nominees at the organizer's
 * request. A wildcard then competes in Round 2 exactly like a finalist.
 *
 * Rules: admins only, a reason is required, at most limit() wildcards per
 * category, and only before that category has any Round 2 score.
 */
class Round2Wildcards
{
    public const SETTING_LIMIT = 'round2_wildcard_limit';

    public const DEFAULT_LIMIT = 1;

    public const MAX_LIMIT = 10;

    /** Stored best_presenter values that do not nominate anyone. */
    private const NO_NOMINATION = ['', '0', 'no', 'n', 'tidak', 'false'];

    public function __construct(private readonly ScoreCalculator $calculator) {}

    /** Wildcards allowed per category; 0 turns the feature off. */
    public static function limit(): int
    {
        $limit = (int) Setting::get(self::SETTING_LIMIT, (string) self::DEFAULT_LIMIT);

        return max(0, min(self::MAX_LIMIT, $limit));
    }

    /**
     * The session's wildcards whose project is still approved.
     *
     * @return Collection<int, Round2Wildcard>
     */
    public function forSession(int $sessionId): Collection
    {
        return Round2Wildcard::with(['project.category', 'addedBy:id,name'])
            ->where('session_id', $sessionId)
            ->whereHas('project', fn ($query) => $query->where('status', Project::STATUS_APPROVED))
            ->orderBy('id')
            ->get();
    }

    /**
     * Round 1 Best Presenter nominations per project.
     *
     * @return Collection<int, int> project id => nominations
     */
    public function nominationCounts(int $sessionId): Collection
    {
        return Score::where('session_id', $sessionId)
            ->where('round_no', 1)
            ->whereNotNull('best_presenter')
            ->whereRaw('LOWER(TRIM(best_presenter)) NOT IN ('.implode(',', array_fill(0, count(self::NO_NOMINATION), '?')).')', self::NO_NOMINATION)
            ->groupBy('project_id')
            ->pluck(DB::raw('COUNT(*)'), 'project_id')
            ->map(fn ($count) => (int) $count);
    }

    /**
     * Projects that could be added: approved, nominated for Best Presenter in
     * Round 1, and not already in Round 2.
     *
     * @return list<array{id: int, pcode: string|null, title: string, category_id: int, nominations: int, avg_r1: float|null}>
     */
    public function candidates(CompetitionSession $session): array
    {
        $nominations = $this->nominationCounts($session->id);
        $inRoundTwo = $this->calculator->getRound2ProjectIds($session);
        $averages = Score::where('session_id', $session->id)
            ->where('round_no', 1)
            ->groupBy('project_id')
            ->pluck(DB::raw('AVG(total)'), 'project_id');

        return array_values(Project::where('session_id', $session->id)
            ->where('status', Project::STATUS_APPROVED)
            ->whereIn('id', $nominations->keys()->all())
            ->whereNotIn('id', $inRoundTwo)
            ->orderBy('pcode')
            ->get(['id', 'pcode', 'title', 'category_id'])
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'pcode' => $project->pcode,
                'title' => $project->title,
                'category_id' => $project->category_id,
                'nominations' => (int) ($nominations[$project->id] ?? 0),
                'avg_r1' => isset($averages[$project->id]) ? round((float) $averages[$project->id], 2) : null,
            ])
            ->sortByDesc('nominations')
            ->all());
    }

    /** Whether any project in the category already has a Round 2 score. */
    public function roundTwoScoringStarted(int $sessionId, int $categoryId): bool
    {
        return Score::where('scores.session_id', $sessionId)
            ->where('scores.round_no', 2)
            ->join('projects', 'projects.id', '=', 'scores.project_id')
            ->where('projects.category_id', $categoryId)
            ->exists();
    }

    /**
     * Add a project to Round 2. Returns an error message, or null on success.
     */
    public function add(CompetitionSession $session, Project $project, string $reason, User $admin): ?string
    {
        if ($session->r2_locked) {
            return 'Round 2 is finalized, so no project can be added.';
        }

        $limit = self::limit();

        if ($limit === 0) {
            return 'Round 2 wildcards are turned off. Set a limit under System Settings to use them.';
        }

        if ($project->session_id !== $session->id || $project->status !== Project::STATUS_APPROVED) {
            return 'Only approved projects in the active session can be added.';
        }

        if (in_array($project->id, $this->calculator->getRound2ProjectIds($session), true)) {
            return 'This project is already in Round 2.';
        }

        if (! $this->nominationCounts($session->id)->has($project->id)) {
            return 'Only projects nominated for Best Presenter in Round 1 can be added.';
        }

        if ($this->roundTwoScoringStarted($session->id, $project->category_id)) {
            return 'Round 2 scoring has started in this category, so no project can be added to it.';
        }

        $used = $this->forSession($session->id)
            ->filter(fn (Round2Wildcard $wildcard) => $wildcard->project?->category_id === $project->category_id)
            ->count();

        if ($used >= $limit) {
            return "This category already has the maximum of {$limit} wildcard ".($limit === 1 ? 'entry' : 'entries').'.';
        }

        Round2Wildcard::create([
            'session_id' => $session->id,
            'project_id' => $project->id,
            'reason' => $reason,
            'added_by' => $admin->id,
        ]);

        AuditLogger::log(
            'ADD_ROUND2_WILDCARD',
            "Added project '{$project->title}' (Code: {$project->pcode}) to Round 2 as a wildcard. Reason: {$reason}"
        );

        return null;
    }

    /**
     * Take a wildcard out of Round 2. Returns an error message, or null on success.
     */
    public function remove(CompetitionSession $session, Project $project): ?string
    {
        $wildcard = Round2Wildcard::where('session_id', $session->id)
            ->where('project_id', $project->id)
            ->first();

        if (! $wildcard) {
            return 'This project is not a Round 2 wildcard.';
        }

        $hasRoundTwo = JudgeAssignment::where('session_id', $session->id)->where('project_id', $project->id)->where('round_no', 2)->exists()
            || Score::where('session_id', $session->id)->where('project_id', $project->id)->where('round_no', 2)->exists();

        if ($hasRoundTwo) {
            return 'Remove its Round 2 judge assignments before taking this wildcard out of Round 2.';
        }

        $wildcard->delete();

        AuditLogger::log(
            'REMOVE_ROUND2_WILDCARD',
            "Removed wildcard project '{$project->title}' (Code: {$project->pcode}) from Round 2."
        );

        return null;
    }
}
