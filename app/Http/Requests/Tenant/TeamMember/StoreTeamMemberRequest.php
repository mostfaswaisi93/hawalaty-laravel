<?php

namespace App\Http\Requests\Tenant\TeamMember;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated explicitly via $this->authorize('create', TeamMember::class)
        // in TeamMemberController.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // "tenant." prefix required on every exists:/unique: rule in
            // this codebase — see Expense\StoreExpenseRequest for the full
            // reasoning.
            'email' => ['required', 'email', 'max:255', Rule::unique('tenant.team_members', 'email')],
            'password' => ['required', 'string', 'min:6'],
            'roleId' => ['required', 'string', 'exists:tenant.roles,id'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
