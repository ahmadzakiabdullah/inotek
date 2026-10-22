<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property int $judge_id
 * @property int $session_id
 * @property int $round_no
 */
class JudgeAssignment extends Model
{
    protected $table = 'judge_assignments';

    protected $fillable = [
        'project_id',
        'judge_id',
        'session_id',
        'round_no',
    ];

    protected $casts = [
        'round_no' => 'integer',
    ];

    /**
     * Get the project assigned.
     */
    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Get the judge assigned.
     */
    /** @return BelongsTo<User, $this> */
    public function judge(): BelongsTo
    {
        return $this->belongsTo(User::class, 'judge_id');
    }

    /**
     * Get the competition session for this assignment.
     */
    /** @return BelongsTo<CompetitionSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CompetitionSession::class, 'session_id');
    }
}
