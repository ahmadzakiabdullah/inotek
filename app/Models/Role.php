<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'label', 'description'])]
class Role extends Model
{
    use HasFactory;

    /**
     * Get the users associated with this role.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
