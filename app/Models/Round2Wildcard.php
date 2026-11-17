<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A project an admin added to Round 2 on top of the automatic shortlist.
 *
 * @property int $id
 * @property int $session_id
 * @property int $project_id
 * @property string $reason
 * @property int|null $added_by
 */
class Round2Wildcard extends Model
{
    protected $fillable = [
        'session_id',
        'project_id',
        'reason',
        'added_by',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
