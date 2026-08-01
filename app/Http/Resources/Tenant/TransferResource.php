<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Transfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'customer' => $this->customer,
            'date' => $this->created_at?->toDateString(),

            'status' => $this->status?->value,

            'items' => TransferLineItemResource::collection($this->whenLoaded('lineItems')),
            'amount' => (float) $this->amount,
            'itemName' => $this->item_name,

            'paymentMethod' => $this->payment_method?->value,

            'hasChange' => $this->has_change,
            'changeMethod' => $this->change_method?->value,
            'changeAccountId' => $this->change_account_id !== null ? (string) $this->change_account_id : null,
            'changeAccountName' => $this->changeAccount?->holder_name,
            'changeAmount' => $this->change_amount !== null ? (float) $this->change_amount : null,

            'accountId' => $this->account_id !== null ? (string) $this->account_id : null,
            'accountName' => $this->account?->holder_name,
            'senderName' => $this->sender_name,
            'senderPhone' => $this->sender_phone,
            'transferredAmount' => $this->transferred_amount !== null ? (float) $this->transferred_amount : null,
        ];
    }
}
