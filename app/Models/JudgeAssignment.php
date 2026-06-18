<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JudgeAssignment extends Model
{
    use HasFactory;

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
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Get the judge assigned.
     */
    public function judge(): BelongsTo
    {
        return $this->belongsTo(User::class, 'judge_id');
    }

    /**
     * Get the competition session for this assignment.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CompetitionSession::class, 'session_id');
    }
}
