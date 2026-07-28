<?php

namespace App\Http\Requests\Admin\Withdrawal;

use App\Http\Requests\Admin\Concerns\ChecksBoxBalance;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreWithdrawalRequest extends FormRequest
{
    use ChecksBoxBalance;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'boxId' => ['required', 'string', 'exists:boxes,id'],
            // `min:0.01` bounds it from below only. The upper bound is the
            // box's own balance, which no static rule can express - see the
            // trait.
            'amount' => ['required', 'numeric', 'min:0.01'],
            'partnerId' => ['required', 'string', 'exists:partners,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->refuseIfBoxCannotAfford($validator);
    }
}
