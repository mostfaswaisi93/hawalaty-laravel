<?php

namespace App\Http\Requests\Tenant\TeamMember;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes', 'required', 'email', 'max:255',
                Rule::unique('tenant.team_members', 'email')->ignore($this->route('teamMember')),
            ],

            'password' => ['nullable', 'string', 'min:6'],

            'roleId' => ['sometimes', 'required', 'string', 'exists:tenant.roles,id'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
