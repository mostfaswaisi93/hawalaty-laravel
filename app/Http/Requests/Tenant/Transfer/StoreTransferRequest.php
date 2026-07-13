<?php

namespace App\Http\Requests\Tenant\Transfer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body shape mirrors the Angular `Transfer` interface's writable fields
 * (D:\Angular\Hawalaty\src\app\data\transfers.ts): customer, items[]
 * (client-computed cart snapshot, trusted as-is except each itemId's
 * existence), plus the cash-scenario fields (hasChange/changeMethod/
 * changeBankId/changeAmount) or bank-scenario fields (bankId/senderName/
 * senderPhone/transferredAmount) depending on paymentMethod. `amount` is
 * intentionally NOT accepted here — TransferService computes it
 * server-side from the line items, never trusting a client-sent total.
 */
class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated explicitly via $this->authorize('create', Transfer::class)
        // in TransferController.
        return true;
    }

    public function rules(): array
    {
        return [
            'customer' => ['required', 'string', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:30'],

            'items' => ['required', 'array', 'min:1'],
            // "tenant." prefix required on every exists:/unique: rule in
            // this codebase — see StoreExpenseRequest for the full
            // reasoning (bare table names resolve against the platform
            // connection, not the dynamic `tenant` one).
            'items.*.itemId' => ['required', 'string', 'exists:tenant.menu_items,id'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],

            'paymentMethod' => ['required', Rule::in(['cash', 'bank'])],

            // --- Cash scenario ---
            'hasChange' => ['required_if:paymentMethod,cash', 'boolean'],
            'changeMethod' => ['required_if:hasChange,1', Rule::in(['cash', 'bank'])],
            'changeBankId' => ['nullable', 'required_if:changeMethod,bank', 'string', 'exists:tenant.banks,id'],
            'changeAmount' => ['nullable', 'required_if:hasChange,1', 'numeric', 'min:0'],

            // --- Bank/wallet transfer scenario ---
            'bankId' => ['nullable', 'required_if:paymentMethod,bank', 'string', 'exists:tenant.banks,id'],
            'senderName' => ['nullable', 'string', 'max:255'],
            'senderPhone' => ['nullable', 'string', 'max:30'],
            'transferredAmount' => ['nullable', 'required_if:paymentMethod,bank', 'numeric', 'min:0'],
        ];
    }
}
