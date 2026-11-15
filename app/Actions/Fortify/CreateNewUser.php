<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * New accounts allowed per IP address per hour. Email addresses are not
     * verified, so this stops a script from creating accounts in bulk while
     * still allowing a few students on a shared campus connection.
     */
    public const MAX_REGISTRATIONS_PER_HOUR = 5;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $throttleKey = 'register:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_REGISTRATIONS_PER_HOUR)) {
            $minutes = (int) ceil(RateLimiter::availableIn($throttleKey) / 60);

            throw ValidationException::withMessages([
                'email' => "Too many accounts have been registered from this network. Please try again in {$minutes} minute(s).",
            ]);
        }

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $roleAttributes = [
            'label' => 'User',
            'description' => 'Standard user / Student.',
        ];

        if (Schema::hasColumn('roles', 'guard_name')) {
            $roleAttributes['guard_name'] = 'web';
        }

        $defaultRoleId = Role::firstOrCreate(
            ['name' => 'user'],
            $roleAttributes,
        )->id;

        $user = User::create([
            'name' => $input['name'],
            'username' => $input['username'],
            'email' => $input['email'],
            'password' => $input['password'],
            'role_id' => $defaultRoleId,
        ]);

        // Count only accounts actually created, so typing mistakes do not lock anyone out.
        RateLimiter::hit($throttleKey, 3600);

        return $user;
    }
}
