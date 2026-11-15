<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Roles whose access depends on their email address: lecturers manage the
     * projects that name them as supervisor, and judges are checked for conflicts
     * of interest by email. Email addresses are not verified, so only an
     * administrator may change them for these roles.
     */
    public const EMAIL_LOCKED_ROLES = ['lecturer', 'judge'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->profileRules($this->user()->id);
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();
                $email = trim((string) $this->input('email'));

                if ($user->hasRole(self::EMAIL_LOCKED_ROLES)
                    && strcasecmp($email, trim((string) $user->email)) !== 0) {
                    $validator->errors()->add(
                        'email',
                        'Lecturer and judge email addresses can only be changed by an administrator.',
                    );
                }
            },
        ];
    }
}
