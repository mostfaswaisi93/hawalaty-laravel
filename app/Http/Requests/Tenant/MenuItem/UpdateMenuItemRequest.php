<?php

namespace App\Http\Requests\Tenant\MenuItem;

use Illuminate\Foundation\Http\FormRequest;

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
            // "tenant." prefix required - see Bank\StoreBankRequest... (see
            // MenuItem\StoreMenuItemRequest for the full reasoning).
            'menuCategoryId' => ['sometimes', 'required', 'string', 'exists:tenant.menu_categories,id'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
