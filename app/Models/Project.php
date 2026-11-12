<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $session_id
 * @property int $category_id
 * @property int $user_id
 * @property string|null $pcode
 * @property string $title
 * @property string $abstract
 * @property int $status
 * @property string|null $award_level
 * @property float|null $round_one_score
 * @property float|null $round_two_score
 * @property-read Category|null $category
 * @property-read User|null $user
 * @property-read Collection<int, TeamMember> $teamMembers
 */
class Project extends Model
{
    use SoftDeletes;

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
     * Hide the award from serialization until the session's results are locked.
     * Use before passing projects to non-admin users.
     */
    public function hideUnpublishedAward(?CompetitionSession $session = null): static
    {
        $session ??= $this->session;

        if (! $session?->r2_locked) {
            $this->makeHidden('award_level');
        }

        return $this;
    }

    /**
     * Get the session that owns the project.
     */
    /** @return BelongsTo<CompetitionSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CompetitionSession::class, 'session_id');
    }

    /**
     * Get the category that owns the project.
     */
    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Get the user/participant who owns/registered the project.
     */
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the team members for the project.
     */
    /** @return HasMany<TeamMember, $this> */
    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'project_id');
    }

    /**
     * Get the scores/evaluations for the project.
     */
    /** @return HasMany<Score, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(Score::class, 'project_id');
    }

    /**
     * Get the judge assignments for this project.
     */
    /** @return HasMany<JudgeAssignment, $this> */
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
