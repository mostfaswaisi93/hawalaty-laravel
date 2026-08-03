<?php

namespace App\Http\Requests\Tenant\Warranty;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Opening a warranty claim.
 *
 * Extracted from the controller so the offline sync channel can validate the
 * same payload against the same rules. A device's events are untrusted input
 * like any other request, and a second copy of the rules living in the sync
 * path is a second copy to drift.
 */
class StoreWarrantyClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'serialId' => ['nullable', 'string', 'exists:tenant.serial_numbers,id'],
            'itemId' => ['required_without:serialId', 'nullable', 'string', 'exists:tenant.menu_items,id'],
            'reference' => ['nullable', 'string', 'max:64'],
            'customerName' => ['required', 'string', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:50'],
            'issue' => ['required', 'string', 'max:2000'],
            'underWarranty' => ['nullable', 'boolean'],
        ];
    }
}
