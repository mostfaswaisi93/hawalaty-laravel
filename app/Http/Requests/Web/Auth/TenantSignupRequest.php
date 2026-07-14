<?php

namespace App\Http\Requests\Web\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class TenantSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `confirmPassword` and `terms` exist only for this web form (see
     * resources/views/auth/signup.blade.php) — the JSON API's SignupRequest
     * (App\Http\Requests\Auth\SignupRequest) has neither. Only the fields
     * App\Services\TenantProvisioner::provision() actually reads
     * (businessName, ownerName, ownerEmail, phone, password, packageId) are
     * passed through to it from the controller.
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
            'confirmPassword' => ['required', 'string', 'same:password'],
            'packageId' => ['nullable', 'string'],
            'terms' => ['accepted'],
        ];
    }
}
