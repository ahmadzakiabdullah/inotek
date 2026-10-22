<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitionSession extends Model
{
    protected $table = 'competition_sessions';

    protected $fillable = [
        'name',
        'is_active',
        'r2_locked',
        'round2_shortlist_limit',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'r2_locked' => 'boolean',
        'round2_shortlist_limit' => 'integer',
    ];

    /**
     * Get the categories scoped by this session.
     */
    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'session_id');
    }
}
