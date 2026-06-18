<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitionSession extends Model
{
    use HasFactory;

    protected $table = 'competition_sessions';

    protected $fillable = [
        'name',
        'is_active',
        'r2_locked',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'r2_locked' => 'boolean',
    ];

    /**
     * Get the categories scoped by this session.
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'session_id');
    }
}
