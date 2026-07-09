<?php

namespace App\Http\Requests\Admin\Payment;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'licenseId' => ['required', 'string', 'exists:licenses,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'boxId' => ['required', 'string', 'exists:boxes,id'],
        ];
    }
}
