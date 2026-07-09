<?php

namespace App\Http\Requests\Tenant\TeamMember;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated explicitly via $this->authorize('update', $teamMember) in
        // TeamMemberController.
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
            // Optional-if-present: an empty/omitted password means "keep
            // the current one" — TeamService strips it when blank.
            'password' => ['nullable', 'string', 'min:6'],
            // "tenant." prefix required — see StoreExpenseRequest.
            'roleId' => ['sometimes', 'required', 'string', 'exists:tenant.roles,id'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
