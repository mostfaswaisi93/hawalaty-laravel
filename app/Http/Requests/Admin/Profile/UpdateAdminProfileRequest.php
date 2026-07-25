<?php

namespace App\Http\Requests\Admin\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Self-service profile + password update for the logged-in Super Admin.
 * Mirrors the tenant-side App\Http\Requests\Web\Settings\UpdateProfileRequest,
 * but the unique check runs against the platform `admins` table (no "tenant."
 * prefix — admins never live in a tenant database).
 */
class UpdateAdminProfileRequest extends FormRequest
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
                Rule::unique('admins', 'email')->ignore(auth('admin')->id()),
            ],
            // Blank means "keep current". `confirmed` pairs with a
            // password_confirmation field on the form.
            'password' => ['nullable', 'string', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
        ];
    }
}
