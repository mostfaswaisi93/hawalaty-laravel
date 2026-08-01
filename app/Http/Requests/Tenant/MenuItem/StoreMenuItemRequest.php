<?php

namespace App\Http\Requests\Tenant\MenuItem;

use App\Services\Tenant\CatalogIcons;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'icon' => ['nullable', 'string', Rule::in(app(CatalogIcons::class)->all())],

            'menuCategoryId' => ['required', 'string', 'exists:tenant.menu_categories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
