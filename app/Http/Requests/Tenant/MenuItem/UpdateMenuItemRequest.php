<?php

namespace App\Http\Requests\Tenant\MenuItem;

use App\Services\Tenant\CatalogIcons;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated explicitly via $this->authorize('update', $menuItem) in
        // MenuItemController.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            // Whitelisted: the value is rendered as a ligature inside a
            // <span class="material-symbols-outlined">, so free text would be a
            // way to put arbitrary content on the page - and an unknown ligature
            // renders as its own literal name, which looks broken.
            'icon' => ['nullable', 'string', Rule::in(app(CatalogIcons::class)->all())],
            // "tenant." prefix required - see Bank\StoreBankRequest... (see
            // MenuItem\StoreMenuItemRequest for the full reasoning).
            'menuCategoryId' => ['sometimes', 'required', 'string', 'exists:tenant.menu_categories,id'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
