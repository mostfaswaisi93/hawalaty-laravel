<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A box was asked to pay out more than it holds.
 *
 * Thrown from inside the locked transaction in WithdrawalService and
 * ExpenseService, which is the only place the answer is authoritative: the
 * FormRequest checks the balance too, but two withdrawals submitted at the same
 * moment can both pass that check and only one of them can be afforded.
 *
 * Carries the numbers so the controller can tell the admin what the shortfall
 * actually is instead of a bare "not allowed".
 */
class InsufficientBoxBalanceException extends RuntimeException
{
    public function __construct(
        public readonly string $boxName,
        public readonly float $available,
        public readonly float $requested,
    ) {
        parent::__construct("Box \"{$boxName}\" holds {$available} but {$requested} was requested.");
    }

    /** The message the admin sees, with both figures. */
    public function userMessage(): string
    {
        return __('رصيد الصندوق :box لا يكفي - المتاح :available ₪ والمطلوب :requested ₪.', [
            'box' => $this->boxName,
            'available' => number_format($this->available, 2),
            'requested' => number_format($this->requested, 2),
        ]);
    }
}
