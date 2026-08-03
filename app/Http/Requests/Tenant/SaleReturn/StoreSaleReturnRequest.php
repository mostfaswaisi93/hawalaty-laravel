<?php

namespace App\Http\Requests\Tenant\SaleReturn;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating a return, as a draft.
 *
 * Shared with the offline sync channel, which validates a device's payload
 * against exactly these rules before it reaches the service. Nothing here moves
 * stock or refunds money - completing the draft does, and that stays online.
 */
class StoreSaleReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::baseRules();
    }

    /**
     * @return array<string, mixed>
     */
    public static function baseRules(): array
    {
        return [
            'transferId' => ['nullable', 'string', 'exists:tenant.transfers,id'],
            'customerName' => ['required', 'string', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:64'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],

            'refundMethod' => ['required', Rule::in(['cash', 'bank', 'store_credit'])],
            'accountId' => ['nullable', 'required_if:refundMethod,bank', 'string', 'exists:tenant.accounts,id'],
            'restockingFee' => ['nullable', 'numeric', 'min:0'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.transferLineItemId' => ['nullable', 'string', 'exists:tenant.transfer_line_items,id'],
            'lines.*.itemId' => ['nullable', 'required_without:lines.*.transferLineItemId', 'string', 'exists:tenant.menu_items,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unitPrice' => ['nullable', 'numeric', 'min:0'],
            'lines.*.condition' => ['nullable', Rule::in(['resalable', 'damaged'])],
            'lines.*.serialId' => ['nullable', 'string', 'exists:tenant.serial_numbers,id'],
            'lines.*.batchId' => ['nullable', 'string', 'exists:tenant.batches,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => __('اختر بندًا واحدًا على الأقل للإرجاع.'),
            'accountId.required_if' => __('حدّد الحساب الذي سيُرد منه المبلغ.'),
        ];
    }
}
