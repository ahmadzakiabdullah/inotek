<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rubric extends Model
{
    use HasFactory;

    protected $table = 'rubrics';

    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Get the items/criteria belonging to the rubric.
     */
    public function items(): HasMany
    {
        return $this->hasMany(RubricItem::class, 'rubric_id');
    }

    /**
     * The categories mapped to this rubric.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_rubric_mapping', 'rubric_id', 'category_id');
    }
}
