<?php

namespace App\Http\Requests\Tenant\Transfer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer' => ['required', 'string', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:30'],

            'notes' => ['nullable', 'string', 'max:500'],

            'items' => ['required', 'array', 'min:1'],

            'items.*.itemId' => ['required', 'string', 'exists:tenant.menu_items,id'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],

            'discount' => ['nullable', 'numeric', 'min:0'],

            'paymentMethod' => ['required', Rule::in(['cash', 'bank'])],

            'hasChange' => ['required_if:paymentMethod,cash', 'boolean'],
            'changeMethod' => ['required_if:hasChange,1', Rule::in(['cash', 'bank'])],
            'changeAccountId' => ['nullable', 'required_if:changeMethod,bank', 'string', 'exists:tenant.accounts,id'],
            'changeAmount' => ['nullable', 'required_if:hasChange,1', 'numeric', 'min:0'],

            'accountId' => ['nullable', 'required_if:paymentMethod,bank', 'string', 'exists:tenant.accounts,id'],
            'senderName' => ['nullable', 'string', 'max:255'],
            'senderPhone' => ['nullable', 'string', 'max:30'],
            'transferredAmount' => ['nullable', 'required_if:paymentMethod,bank', 'numeric', 'min:0'],

            'referenceNumber' => ['nullable', 'required_if:paymentMethod,bank', 'string', 'max:100', Rule::unique('tenant.transfers', 'reference_number')],
        ];
    }

}
