<?php

namespace App\Http\Requests\Tenant\Catalog;

use App\Services\Tenant\CatalogRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One request for every catalog's store AND update, because every catalog row
 * is the same shape: a `name`, an optional `symbol` (currencies only), and an
 * `active` flag. The `{catalog}` route segment decides which table `name` must
 * be unique in and whether `symbol` is required.
 *
 * Store requires `name`; update relaxes it to `sometimes` so the active toggle
 * - which posts only `active` - is a valid partial update (mirrors
 * UpdateBankRequest).
 */
class CatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated in CatalogController::canManage() (BanksManage).
        return true;
    }

    public function rules(): array
    {
        $registry = app(CatalogRegistry::class);
        $catalog = (string) $this->route('catalog');

        // Unknown catalog -> 404 before any rule references a missing model.
        $meta = $registry->get($catalog);
        $table = (new $meta['model'])->getTable();

        $creating = $this->isMethod('post');
        // `name` uniqueness counts soft-deleted rows (Rule::unique queries the
        // raw table) exactly as the DB unique index does, so the two never
        // disagree - same trade-off as banks.code.
        $presence = $creating ? ['required'] : ['sometimes', 'required'];

        $rules = [
            'name' => [
                ...$presence,
                'string',
                'max:255',
                Rule::unique('tenant.'.$table, 'name')->ignore($this->route('id')),
            ],
            'active' => ['sometimes', 'boolean'],
        ];

        if ($meta['symbol']) {
            $rules['symbol'] = [...$presence, 'string', 'max:8'];
        }

        // Banks / wallets / cash boxes: the currencies they support. Optional
        // (`sometimes`), so the active-toggle partial update never clears them.
        if ($meta['currencies']) {
            $rules['currencies'] = ['sometimes', 'nullable', 'array'];
            $rules['currencies.*'] = ['string', Rule::exists('tenant.currencies', 'id')];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.unique' => __('هذا الاسم مستخدم بالفعل.'),
        ];
    }
}
