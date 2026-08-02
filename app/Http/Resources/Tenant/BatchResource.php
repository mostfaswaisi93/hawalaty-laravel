<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Batch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the {batch, on_hand} rows BatchService produces.
 *
 * The balance is not a column on the lot - it is derived from the ledger - so it
 * arrives alongside the model rather than on it, and the resource takes both.
 */
class BatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $batch = $this->resource['batch'] ?? $this->resource;

        if (! $batch instanceof Batch) {
            return [];
        }

        return [
            'id' => (string) $batch->id,
            'batchNumber' => $batch->batch_number,

            'item' => [
                'id' => (string) $batch->menu_item_id,
                'name' => $batch->item?->name,
            ],

            'expiresAt' => $batch->expires_at?->toDateString(),
            'daysLeft' => $batch->daysLeft(),
            'expired' => $batch->isExpired(),
            'status' => $batch->status(),

            'onHand' => round((float) ($this->resource['on_hand'] ?? 0), 3),
            'supplier' => $batch->supplier?->name,
        ];
    }
}
