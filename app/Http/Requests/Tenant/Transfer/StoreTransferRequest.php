<?php

namespace App\Http\Requests\Tenant\Transfer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body shape mirrors the Angular `Transfer` interface's writable fields
 * (D:\Angular\Hawalaty\src\app\data\transfers.ts): customer, items[], plus
 * the cash-scenario fields (hasChange/changeMethod/changeAccountId/
 * changeAmount) or transfer-scenario fields (accountId/senderName/senderPhone/
 * transferredAmount) depending on paymentMethod. `amount` is intentionally
 * NOT accepted here - TransferService computes it server-side from the line
 * items. `items.*.price`/`items.*.name` are validated as present here for a
 * well-formed request, but TransferService::create() ignores both and
 * re-derives them from the canonical MenuItem row per `itemId` - a
 * client-submitted price/name is never trusted, only the item's identity.
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

            // Free text, NOT validated against the constant lists. The dropdowns
            // are a shortcut for filling this in; a tenant may also type
            // something no list covers, and deleting a list later must not
            // invalidate orders that quoted it.
            'notes' => ['nullable', 'string', 'max:500'],

            'items' => ['required', 'array', 'min:1'],
            // "tenant." prefix required on every exists:/unique: rule in
            // this codebase - see StoreExpenseRequest for the full
            // reasoning (bare table names resolve against the platform
            // connection, not the dynamic `tenant` one).
            'items.*.itemId' => ['required', 'string', 'exists:tenant.menu_items,id'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],

            // Clamped to the line-item subtotal in TransferService - a discount
            // bigger than the order would drive `amount` negative.
            'discount' => ['nullable', 'numeric', 'min:0'],

            'paymentMethod' => ['required', Rule::in(['cash', 'bank'])],

            // --- Cash scenario ---
            'hasChange' => ['required_if:paymentMethod,cash', 'boolean'],
            'changeMethod' => ['required_if:hasChange,1', Rule::in(['cash', 'bank'])],
            'changeAccountId' => ['nullable', 'required_if:changeMethod,bank', 'string', 'exists:tenant.accounts,id'],
            'changeAmount' => ['nullable', 'required_if:hasChange,1', 'numeric', 'min:0'],

            // --- Bank/wallet transfer scenario ---
            // The account the money landed in. Which of bank/wallet it was is
            // carried by that account's own `kind`, not by a second field.
            'accountId' => ['nullable', 'required_if:paymentMethod,bank', 'string', 'exists:tenant.accounts,id'],
            'senderName' => ['nullable', 'string', 'max:255'],
            'senderPhone' => ['nullable', 'string', 'max:30'],
            'transferredAmount' => ['nullable', 'required_if:paymentMethod,bank', 'numeric', 'min:0'],
            // The slip's own reference. Unique: the same reference twice means
            // the same transfer was booked twice.
            'referenceNumber' => ['nullable', 'required_if:paymentMethod,bank', 'string', 'max:100', Rule::unique('tenant.transfers', 'reference_number')],
        ];
    }

}
