<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RubricItem extends Model
{
    use HasFactory;

    protected $table = 'rubric_items';

    protected $fillable = [
        'rubric_id',
        'section',
        'code',
        'criteria_name',
        'description',
        'weight',
        'max_points',
        'scale_descriptions',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'max_points' => 'integer',
        'scale_descriptions' => 'array',
    ];

    /**
     * Get the rubric that owns the item.
     */
    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class, 'rubric_id');
    }
}
