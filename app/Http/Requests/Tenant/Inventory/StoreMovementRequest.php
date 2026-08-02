<?php

namespace App\Http\Requests\Tenant\Inventory;

use App\Enums\StockMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'itemId' => ['required', 'string', 'exists:tenant.menu_items,id'],

            // Only the three a human posts by hand. Sales, receipts and count
            // variances arrive through their own flows and must not be typeable.
            'type' => ['required', Rule::in([
                StockMovementType::Adjustment->value,
                StockMovementType::Waste->value,
                StockMovementType::Transfer->value,
            ])],

            // An adjustment carries the counted total, a write-off and a transfer
            // carry a delta - hence min:0 rather than a positive floor.
            'quantity' => ['required', 'numeric', 'min:0', 'max:99999999'],

            'locationId' => [
                Rule::requiredIf(fn () => $this->input('type') === StockMovementType::Transfer->value),
                'nullable', 'string', 'exists:tenant.locations,id',
            ],

            'toLocationId' => [
                Rule::requiredIf(fn () => $this->input('type') === StockMovementType::Transfer->value),
                'nullable', 'string', 'different:locationId', 'exists:tenant.locations,id',
            ],

            'reason' => [
                Rule::requiredIf(fn () => in_array($this->input('type'), [
                    StockMovementType::Adjustment->value,
                    StockMovementType::Waste->value,
                ], true)),
                'nullable', 'string', 'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => __('اذكر سبب التسوية أو التلف — الحركة بدون سبب لا تُقبل.'),
            'toLocationId.different' => __('اختر موقعًا مختلفًا للنقل إليه.'),
        ];
    }
}
