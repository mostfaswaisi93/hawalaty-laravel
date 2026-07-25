<?php

namespace App\Http\Requests\Admin\License;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Same shape as App\Http\Requests\Auth\SignupRequest (both ultimately feed
 * App\Services\TenantProvisioner::provision()) - an admin creating a license
 * on a customer's behalf is functionally the same provisioning operation as
 * self-service signup, just triggered from the Super Admin panel instead of
 * a public endpoint.
 */
class StoreLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'businessName' => ['required', 'string', 'max:255'],
            'ownerName' => ['required', 'string', 'max:255'],
            'ownerEmail' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()],
            'packageId' => ['nullable', 'string', 'exists:packages,id'],
        ];
    }
}
