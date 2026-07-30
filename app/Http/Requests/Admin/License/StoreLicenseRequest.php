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

            // The tenant database is created by the operator in cPanel first
            // (shared hosting forbids CREATE DATABASE from the app), then linked
            // here. Identifiers are whitelisted because they are interpolated
            // into a connection config, never bound. Host/port default when left
            // blank. See App\Services\TenantProvisioner (link mode).
            'dbName' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
            'dbUsername' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
            'dbPassword' => ['required', 'string', 'max:255'],
            'dbHost' => ['nullable', 'string', 'max:255'],
            'dbPort' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dbName.regex' => __('اسم قاعدة البيانات: حروف إنجليزية وأرقام وشرطة سفلية فقط.'),
            'dbUsername.regex' => __('اسم المستخدم: حروف إنجليزية وأرقام وشرطة سفلية فقط.'),
        ];
    }
}
