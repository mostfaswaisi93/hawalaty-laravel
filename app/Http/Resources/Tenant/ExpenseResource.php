<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
