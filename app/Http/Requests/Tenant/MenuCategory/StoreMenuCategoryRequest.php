<?php

namespace App\Http\Requests\Tenant\MenuCategory;

use App\Services\Tenant\CatalogIcons;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'name' => ['required', 'string', 'max:255', Rule::unique('tenant.menu_categories', 'name')],

            'icon' => ['nullable', 'string', Rule::in(app(CatalogIcons::class)->all())],
        ];
    }
}
