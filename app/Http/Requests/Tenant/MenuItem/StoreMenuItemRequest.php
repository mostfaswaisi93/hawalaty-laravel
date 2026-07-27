<?php

namespace App\Http\Requests\Tenant\MenuItem;

use App\Services\Tenant\CatalogIcons;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `menuCategoryId` -> `menu_category_id` is mapped by MenuService (not
 * validated()) since the Service layer owns all persistence mapping in
 * this architecture.
 */
class StoreMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated explicitly via $this->authorize('create', MenuItem::class)
        // in MenuItemController.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Whitelisted: the value is rendered as a ligature inside a
            // <span class="material-symbols-outlined">, so free text would be a
            // way to put arbitrary content on the page - and an unknown ligature
            // renders as its own literal name, which looks broken.
            'icon' => ['nullable', 'string', Rule::in(app(CatalogIcons::class)->all())],
            // Physical DB-per-tenant isolation guarantees there's only one
            // tenant's categories reachable per request, so no extra
            // tenant_id scoping is needed on top of exists:... - BUT the
            // "tenant." connection prefix is still required: a bare
            // exists:menu_categories,id resolves against the app's
            // DEFAULT connection (platform, where this table doesn't even
            // exist), not the dynamic `tenant` connection
            // ResolveTenantConnection configured for this request.
            'menuCategoryId' => ['required', 'string', 'exists:tenant.menu_categories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
