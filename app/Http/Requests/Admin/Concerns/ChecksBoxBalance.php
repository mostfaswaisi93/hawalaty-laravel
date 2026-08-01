<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\Box;
use Illuminate\Contracts\Validation\Validator;

trait ChecksBoxBalance
{
    protected function refuseIfBoxCannotAfford(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->hasAny(['boxId', 'amount'])) {
                return;
            }

            $box = Box::find($this->input('boxId'));

            if ($box === null) {
                return;
            }

            $amount = (string) $this->input('amount');

            if (bccomp((string) $box->box_balance, $amount, 2) < 0) {
                $validator->errors()->add('amount', __('رصيد الصندوق :box لا يكفي - المتاح :available ₪ والمطلوب :requested ₪.', [
                    'box' => $box->box_name,
                    'available' => number_format((float) $box->box_balance, 2),
                    'requested' => number_format((float) $amount, 2),
                ]));
            }
        });
    }
}
