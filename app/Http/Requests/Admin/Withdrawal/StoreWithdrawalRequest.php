<?php

namespace App\Http\Requests\Admin\Withdrawal;

use Illuminate\Foundation\Http\FormRequest;

class StoreWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'boxId' => ['required', 'string', 'exists:boxes,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'partnerId' => ['required', 'string', 'exists:partners,id'],
        ];
    }
}
