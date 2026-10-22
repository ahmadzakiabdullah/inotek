<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int|null $session_id
 * @property string $code
 * @property string $name
 * @property bool $allow_team
 */
class Category extends Model
{
    protected $table = 'categories';

    protected $fillable = [
        'session_id',
        'code',
        'name',
        'allow_team',
    ];

    protected $casts = [
        'allow_team' => 'boolean',
    ];

    /**
     * Get the session that owns this category.
     */
    /** @return BelongsTo<CompetitionSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CompetitionSession::class, 'session_id');
    }

    /**
     * The rubrics associated with this category.
     */
    /** @return BelongsToMany<Rubric, $this> */
    public function rubrics(): BelongsToMany
    {
        return $this->belongsToMany(Rubric::class, 'category_rubric_mapping', 'category_id', 'rubric_id');
    }
}
