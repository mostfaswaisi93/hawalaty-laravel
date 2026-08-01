<?php

namespace App\Http\Requests\Tenant\Role;

use App\Enums\PermissionKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
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
