<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    use HasFactory;

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
    public function session(): BelongsTo
    {
        return $this->belongsTo(CompetitionSession::class, 'session_id');
    }

    /**
     * The rubrics associated with this category.
     */
    public function rubrics(): BelongsToMany
    {
        return $this->belongsToMany(Rubric::class, 'category_rubric_mapping', 'category_id', 'rubric_id');
    }
}
