<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'projects';

    // Status Constants
    public const STATUS_NEW = 1;

    public const STATUS_EDIT = 2;

    public const STATUS_SUBMITTED = 3;

    public const STATUS_APPROVED = 4;

    public const STATUS_CANCELLED = 6;

    protected $fillable = [
        'session_id',
        'category_id',
        'user_id',
        'pcode',
        'title',
        'abstract',
        'poster_url',
        'video_url',
        'institution_type',
        'status',
        'supervisor_name',
        'supervisor_email',
        'supervisor_phone',
        'admin_comments',
        'certificate_hash',
        'award_level',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    /**
     * Get the session that owns the project.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CompetitionSession::class, 'session_id');
    }

    /**
     * Get the category that owns the project.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Get the user/participant who owns/registered the project.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the team members for the project.
     */
    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'project_id');
    }

    /**
     * Get the scores/evaluations for the project.
     */
    public function scores(): HasMany
    {
        return $this->hasMany(Score::class, 'project_id');
    }

    /**
     * Get the judge assignments for this project.
     */
    public function judgeAssignments(): HasMany
    {
        return $this->hasMany(JudgeAssignment::class, 'project_id');
    }

    /**
     * Helper to get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_NEW => 'New Project',
            self::STATUS_EDIT => 'Edit Project',
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Unknown',
        };
    }
}
