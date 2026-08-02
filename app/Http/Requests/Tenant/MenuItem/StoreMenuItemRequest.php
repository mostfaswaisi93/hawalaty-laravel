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
        return array_merge([
            'name' => ['required', 'string', 'max:255'],

            'icon' => ['nullable', 'string', Rule::in(app(CatalogIcons::class)->all())],

            'menuCategoryId' => ['required', 'string', 'exists:tenant.menu_categories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ], UpdateMenuItemRequest::commerceRules());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'atcCode.regex' => __('رمز ATC يتكوّن من حرف ورقمين وحرفين ورقمين — مثال: N02BE01.'),
            'barcode.unique' => __('هذا الباركود مستعمَل لصنف آخر.'),
            'sku.unique' => __('رمز الصنف مستعمَل لصنف آخر.'),
        ];
    }
}
