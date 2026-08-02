<?php

namespace App\Http\Requests\Tenant\MenuItem;

use App\Enums\TaxCategory;
use App\Enums\UnitOfMeasure;
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
        return array_merge([
            'name' => ['sometimes', 'required', 'string', 'max:255'],

            'icon' => ['nullable', 'string', Rule::in(app(CatalogIcons::class)->all())],

            'menuCategoryId' => ['sometimes', 'required', 'string', 'exists:tenant.menu_categories,id'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ], self::commerceRules($this->route('item')));
    }

    /**
     * Everything past name and price.
     *
     * Shared with the store request so a field cannot be validated one way on
     * creation and another on edit - the pair drifting apart is how a value the
     * catalogue rejects gets in through the other door.
     *
     * @return array<string, mixed>
     */
    public static function commerceRules(?string $itemId = null): array
    {
        return [
            'kind' => ['sometimes', Rule::in(['goods', 'service'])],

            // Unique across the catalogue: a barcode that names two items is a
            // barcode that names neither, and the till would pick arbitrarily.
            'barcode' => ['nullable', 'string', 'max:64', self::uniqueIgnoring('barcode', $itemId)],
            'sku' => ['nullable', 'string', 'max:64', self::uniqueIgnoring('sku', $itemId)],
            'pluCode' => ['nullable', 'string', 'max:16'],

            // UN/CEFACT Rec 20. A closed list because the invoice carries it and
            // a validator on the other end will reject an invented code.
            'unitCode' => ['sometimes', Rule::enum(UnitOfMeasure::class)],
            'purchaseUnitCode' => ['nullable', Rule::enum(UnitOfMeasure::class)],
            'purchaseConversion' => ['nullable', 'numeric', 'gt:0'],

            'taxRate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'taxCategory' => ['nullable', Rule::enum(TaxCategory::class)],
            'taxIncluded' => ['sometimes', 'boolean'],

            'cost' => ['nullable', 'numeric', 'min:0'],
            'trackStock' => ['sometimes', 'boolean'],
            'reorderPoint' => ['nullable', 'numeric', 'min:0'],

            'trackSerials' => ['sometimes', 'boolean'],
            'warrantyMonths' => ['nullable', 'integer', 'min:0', 'max:600'],

            'serviceMinutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'coreCharge' => ['nullable', 'numeric', 'min:0'],

            'isPrescription' => ['sometimes', 'boolean'],
            'controlSchedule' => ['nullable', 'string', 'max:16'],

            // WHO ATC: one letter, two digits, two letters, two digits.
            'atcCode' => ['nullable', 'string', 'max:16', 'regex:/^[A-Z]\d{2}[A-Z]{2}\d{2}$/i'],
            'activeIngredient' => ['nullable', 'string', 'max:255'],
            'strength' => ['nullable', 'string', 'max:64'],
            'dosageForm' => ['nullable', 'string', 'max:64'],
            'coldChain' => ['sometimes', 'boolean'],
        ];
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
            'purchaseConversion.gt' => __('معامل التحويل يجب أن يكون أكبر من صفر.'),
        ];
    }

    private static function uniqueIgnoring(string $column, ?string $itemId): mixed
    {
        $rule = Rule::unique('tenant.menu_items', $column);

        return $itemId ? $rule->ignore($itemId) : $rule;
    }
}
