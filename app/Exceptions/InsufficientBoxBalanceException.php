<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientBoxBalanceException extends RuntimeException
{
    public function __construct(
        public readonly string $boxName,
        public readonly float $available,
        public readonly float $requested,
    ) {
        parent::__construct("Box \"{$boxName}\" holds {$available} but {$requested} was requested.");
    }

    public function userMessage(): string
    {
        return __('رصيد الصندوق :box لا يكفي - المتاح :available ₪ والمطلوب :requested ₪.', [
            'box' => $this->boxName,
            'available' => number_format($this->available, 2),
            'requested' => number_format($this->requested, 2),
        ]);
    }
}
