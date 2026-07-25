<?php

namespace App\Http\Requests\Tenant\MenuItem;

use Illuminate\Foundation\Http\FormRequest;

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
