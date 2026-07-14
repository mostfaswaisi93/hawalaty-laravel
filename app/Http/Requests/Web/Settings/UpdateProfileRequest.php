<?php

namespace App\Http\Requests\Web\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Self-service profile + password update for the logged-in tenant member.
 * Deliberately excludes roleId/active (a member must never be able to change
 * their own role or reactivate themselves via this form). The unique email
 * rule ignores the current member's own id — the "tenant." prefix keeps the
 * check on the tenant connection, not the platform DB.
 */
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
            // Blank means "keep current". `confirmed` pairs with a
            // password_confirmation field on the form.
            'password' => ['nullable', 'string', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
        ];
    }
}
