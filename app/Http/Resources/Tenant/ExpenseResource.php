<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes App\Models\Tenant\Expense to match the Angular `Expense` interface
 * in D:\Angular\Hawalaty\src\app\data\expenses.ts exactly, including the
 * denormalized `accountName` (resolved from the account relation, not stored).
 * Controllers must eager-load `account` before returning this resource.
 *
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'description' => $this->description,
            'amount' => (float) $this->amount,
            'accountId' => (string) $this->account_id,
            'accountName' => $this->account?->holder_name,
            'date' => $this->date?->toDateString(),
        ];
    }
}
