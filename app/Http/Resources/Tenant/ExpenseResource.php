<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes App\Models\Tenant\Expense to match the Angular `Expense` interface
 * in D:\Angular\Hawalaty\src\app\data\expenses.ts exactly, including the
 * denormalized `bankName` (resolved from the bank relation, not stored).
 * Controllers must eager-load `bank` before returning this resource.
 *
 * @mixin \App\Models\Tenant\Expense
 */
class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'description' => $this->description,
            'amount' => (float) $this->amount,
            'bankId' => (string) $this->bank_id,
            'bankName' => $this->bank?->name,
            'date' => $this->date?->toDateString(),
        ];
    }
}
