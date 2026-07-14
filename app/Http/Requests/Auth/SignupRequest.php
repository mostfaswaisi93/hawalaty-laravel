<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `phone` is intentionally accepted but not persisted anywhere — the
     * `licenses` table (BACKEND_PROMPT.md section 3) has no column for it.
     * See the provisioning task report for this gap; not silently adding an
     * unplanned migration for it here.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'businessName' => ['required', 'string', 'max:255'],
            'ownerName' => ['required', 'string', 'max:255'],
            'ownerEmail' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()],
            'packageId' => ['nullable', 'string'],
        ];
    }
}
