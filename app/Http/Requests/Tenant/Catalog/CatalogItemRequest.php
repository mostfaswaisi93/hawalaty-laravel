<?php

namespace App\Http\Requests\Tenant\Catalog;

use App\Services\Tenant\CatalogRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $registry = app(CatalogRegistry::class);
        $catalog = (string) $this->route('catalog');

        $meta = $registry->get($catalog);
        $table = (new $meta['model'])->getTable();

        $creating = $this->isMethod('post');

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
