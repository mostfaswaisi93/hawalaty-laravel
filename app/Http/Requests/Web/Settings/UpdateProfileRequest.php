<?php

namespace App\Http\Requests\Web\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('tenant.team_members', 'email')->ignore(auth('tenant')->id()),
            ],

            'password' => ['nullable', 'string', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
        ];
    }
}
