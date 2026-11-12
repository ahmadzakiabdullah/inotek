<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $username
 * @property string $name
 * @property string $email
 * @property int|null $role_id
 * @property-read Role|null $role
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 */
#[Fillable(['name', 'username', 'email', 'password', 'role_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * The legacy users table has no created_at or updated_at columns.
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Get the role associated with the user.
     */
    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Scope users to a role name without relying on role table IDs.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeWithRole(Builder $query, string $role): Builder
    {
        return $query->whereHas('role', fn (Builder $roleQuery) => $roleQuery->where('name', $role));
    }

    /**
     * Check if the user has a specific role.
     *
     * @param  string|array<int, string>  $roles
     */
    public function hasRole(string|array $roles): bool
    {
        if (! $this->relationLoaded('role')) {
            $this->load('role');
        }

        $roleName = $this->role?->name;

        if (is_array($roles)) {
            return in_array($roleName, $roles);
        }

        return $roleName === $roles;
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Get the scores/evaluations created by this user as a judge.
     */
    /** @return HasMany<Score, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(Score::class, 'judge_id');
    }

    /**
     * Get the judge assignments for this user as a judge.
     */
    /** @return HasMany<JudgeAssignment, $this> */
    public function judgeAssignments(): HasMany
    {
        return $this->hasMany(JudgeAssignment::class, 'judge_id');
    }
}
