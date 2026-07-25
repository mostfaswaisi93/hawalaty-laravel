<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Plain email+password login for platform staff - no license code involved,
 * unlike tenant login (App\Http\Requests\Auth\LoginRequest), since Admin
 * always lives on the default `mysql` connection. See BACKEND_PROMPT.md's
 * task prompt, "Admin authentication".
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
