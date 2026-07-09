<?php

namespace App\Http\Requests\Web\Auth;

use Illuminate\Foundation\Http\FormRequest;

class TenantLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'licenseCode' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }
}
