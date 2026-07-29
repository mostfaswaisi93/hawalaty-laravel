<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Transfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes App\Models\Tenant\Transfer to match the Angular `Transfer`
 * interface in D:\Angular\Hawalaty\src\app\data\transfers.ts exactly.
 * Controllers must eager-load `lineItems`, `bank`, and `changeBank` before
 * returning this resource (or a collection of it) to avoid N+1 queries.
 *
 * `date` has no dedicated column - it maps to `created_at`, formatted as a
 * plain ISO date to match the mock data shape ("2026-07-01").
 *
 * @mixin Transfer
 */
class TransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'customer' => $this->customer,
            'date' => $this->created_at?->toDateString(),
            // Null-safe on both enums, deliberately. They are never null on a
            // fully loaded order - but a PARTIAL one (a narrowed ->select that
            // omits the column) used to take the whole response down with a
            // 500 over a single field. One odd row degrading to null is a
            // smaller failure than an entire list disappearing.
            'status' => $this->status?->value,

            'items' => TransferLineItemResource::collection($this->whenLoaded('lineItems')),
            'amount' => (float) $this->amount,
            'itemName' => $this->item_name,

            'paymentMethod' => $this->payment_method?->value,

            // --- Cash scenario ---
            'hasChange' => $this->has_change,
            'changeMethod' => $this->change_method?->value,
            'changeBankId' => $this->change_bank_id !== null ? (string) $this->change_bank_id : null,
            'changeBankName' => $this->changeBank?->name,
            'changeAmount' => $this->change_amount !== null ? (float) $this->change_amount : null,

            // --- Bank/wallet transfer scenario ---
            'bankId' => $this->bank_id !== null ? (string) $this->bank_id : null,
            'bankName' => $this->bank?->name,
            'senderName' => $this->sender_name,
            'senderPhone' => $this->sender_phone,
            'transferredAmount' => $this->transferred_amount !== null ? (float) $this->transferred_amount : null,
        ];
    }
}
