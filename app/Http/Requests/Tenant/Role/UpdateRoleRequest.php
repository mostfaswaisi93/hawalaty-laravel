<?php

namespace App\Http\Requests\Tenant\Role;

use App\Enums\PermissionKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `is_system` roles are already blocked at the authorization layer
 * (RolePolicy::update()) and again defensively in RoleService — this
 * request only validates shape, not that rule.
 */
class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated explicitly via $this->authorize('update', $role) in
        // RoleController.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', Rule::in(array_column(PermissionKey::cases(), 'value'))],
        ];
    }
}
