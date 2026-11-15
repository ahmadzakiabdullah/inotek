<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();

                // Deleting the last administrator would leave nobody able to manage the system.
                if ($user->hasRole('admin')
                    && ! User::withRole('admin')->whereKeyNot($user->id)->exists()) {
                    $validator->errors()->add(
                        'password',
                        'You are the only administrator. Assign another administrator before deleting your account.',
                    );
                }
            },
        ];
    }
}
