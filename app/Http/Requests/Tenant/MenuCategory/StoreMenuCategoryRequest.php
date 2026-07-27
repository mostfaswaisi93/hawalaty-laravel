<?php

namespace App\Http\Requests\Tenant\MenuCategory;

use App\Services\Tenant\CatalogIcons;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated explicitly via $this->authorize('create', MenuCategory::class)
        // in MenuCategoryController.
        return true;
    }

    public function rules(): array
    {
        return [
            // Physical DB-per-tenant isolation means this table only ever
            // contains this tenant's own rows (unlike the superseded
            // prototype, which needed an explicit tenant_id scope closure
            // here) - BUT the "tenant." connection prefix is still
            // required: Rule::unique() otherwise resolves against the
            // app's DEFAULT connection (platform), not the dynamic
            // `tenant` connection ResolveTenantConnection configured for
            // this request. Verified via a real 500 error during this
            // task's curl testing before this fix.
            'name' => ['required', 'string', 'max:255', Rule::unique('tenant.menu_categories', 'name')],
            // Whitelisted: the value is rendered as a ligature inside a
            // <span class="material-symbols-outlined">, so free text would be a
            // way to put arbitrary content on the page - and an unknown ligature
            // renders as its own literal name, which looks broken.
            'icon' => ['nullable', 'string', Rule::in(app(CatalogIcons::class)->all())],
        ];
    }
}
