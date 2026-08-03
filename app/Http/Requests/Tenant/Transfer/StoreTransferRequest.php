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

            /*
             * Fractional, and floored just above zero rather than at one: a till
             * that cannot ring up 1.35 kg of tomatoes or 22.7 litres of diesel
             * cannot serve four of the twelve trades. Whole-number units are
             * enforced by the unit's own step in the UI, not by refusing
             * decimals outright here.
             */
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.price' => ['required', 'numeric', 'min:0'],

            // Ids only. What each option costs is looked up server-side.
            'items.*.modifiers' => ['nullable', 'array'],
            'items.*.modifiers.*' => ['string', 'exists:tenant.modifiers,id'],

            // Which physical units are leaving. Existence and availability are
            // checked against the item in the service; here we only take them.
            'items.*.serials' => ['nullable', 'array'],
            'items.*.serials.*' => ['string', 'max:64'],

            // What the law wants recorded alongside a dispensed medicine. Which
            // of these are actually required depends on the item, so the check
            // lives in the service where the item is known.
            'items.*.dispensing' => ['nullable', 'array'],
            'items.*.dispensing.patientName' => ['nullable', 'string', 'max:255'],
            'items.*.dispensing.patientPhone' => ['nullable', 'string', 'max:50'],
            'items.*.dispensing.patientIdNumber' => ['nullable', 'string', 'max:64'],
            'items.*.dispensing.prescriberName' => ['nullable', 'string', 'max:255'],
            'items.*.dispensing.prescriberLicence' => ['nullable', 'string', 'max:64'],
            'items.*.dispensing.prescriptionNumber' => ['nullable', 'string', 'max:64'],
            'items.*.dispensing.prescriptionDate' => ['nullable', 'date'],

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
