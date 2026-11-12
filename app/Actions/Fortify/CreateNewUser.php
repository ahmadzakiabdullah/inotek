<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
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

        return User::create([
            'name' => $input['name'],
            'username' => $input['username'],
            'email' => $input['email'],
            'password' => $input['password'],
            'role_id' => $defaultRoleId,
        ]);
    }
}
