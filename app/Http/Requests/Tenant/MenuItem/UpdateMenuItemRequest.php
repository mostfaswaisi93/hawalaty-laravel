<?php

namespace App\Http\Requests\Tenant\MenuItem;

use App\Enums\TaxCategory;
use App\Services\Tenant\CatalogIcons;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],

            'icon' => ['nullable', 'string', Rule::in(app(CatalogIcons::class)->all())],

            'menuCategoryId' => ['sometimes', 'required', 'string', 'exists:tenant.menu_categories,id'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],

            'taxRate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'taxCategory' => ['nullable', Rule::enum(TaxCategory::class)],
            'taxIncluded' => ['sometimes', 'boolean'],

            'active' => ['sometimes', 'boolean'],
        ];
    }
}
