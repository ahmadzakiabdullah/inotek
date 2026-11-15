<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int $judge_id
 * @property int $session_id
 * @property int $round_no
 * @property float $total
 * @property array<int|string, int|float|string>|null $score_details
 * @property string|null $comments
 * @property string|null $best_presenter
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Score extends Model
{
    protected $table = 'scores';

    protected $fillable = [
        'project_id',
        'judge_id',
        'session_id',
        'round_no',
        'total',
        'score_details',
        'comments',
        'best_presenter',
    ];

    protected $casts = [
        'round_no' => 'integer',
        'total' => 'float',
        'score_details' => 'array',
    ];

    /**
     * Get the project evaluated by this score.
     */
    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Get the judge who evaluated this score.
     */
    /** @return BelongsTo<User, $this> */
    public function judge(): BelongsTo
    {
        return $this->belongsTo(User::class, 'judge_id');
    }

    /**
     * Get the competition session for this score.
     */
    /** @return BelongsTo<CompetitionSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CompetitionSession::class, 'session_id');
    }
}
