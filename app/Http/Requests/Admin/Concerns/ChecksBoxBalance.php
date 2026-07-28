<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\Box;
use Illuminate\Contracts\Validation\Validator;

/**
 * Refuses an amount larger than the chosen box holds.
 *
 * Shared by the two "money out of a box" requests - a partner withdrawal and a
 * platform expense - because a box going negative is the same defect either way.
 *
 * This is the FRIENDLY half of the guard. It reads the balance outside any lock,
 * so two submissions racing each other can both pass it; the authoritative check
 * lives inside the services' locked transaction and throws
 * InsufficientBoxBalanceException. This one exists so the ordinary case - one
 * admin typing too large a number - comes back as a field error rather than an
 * exception path.
 */
trait ChecksBoxBalance
{
    protected function refuseIfBoxCannotAfford(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Only meaningful once boxId and amount are themselves valid.
            if ($validator->errors()->hasAny(['boxId', 'amount'])) {
                return;
            }

            $box = Box::find($this->input('boxId'));

            if ($box === null) {
                return;
            }

            $amount = (string) $this->input('amount');

            // bccomp, not float arithmetic: these are decimal(12,2) money
            // columns, and 0.1 + 0.2 is exactly the kind of comparison that
            // must not depend on binary rounding.
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
