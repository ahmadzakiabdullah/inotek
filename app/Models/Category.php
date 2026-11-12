<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $allow_team
 */
class Category extends Model
{
    protected $table = 'categories';

    protected $fillable = [
        'code',
        'name',
        'allow_team',
    ];

    protected $casts = [
        'allow_team' => 'boolean',
    ];

    /**
     * The rubrics associated with this category.
     */
    /** @return BelongsToMany<Rubric, $this> */
    public function rubrics(): BelongsToMany
    {
        return $this->belongsToMany(Rubric::class, 'category_rubric_mapping', 'category_id', 'rubric_id')->withPivot('session_id');
    }

    /** @return BelongsToMany<Rubric, $this> */
    public function rubricsForSession(int $sessionId): BelongsToMany
    {
        return $this->rubrics()->wherePivot('session_id', $sessionId);
    }
}
