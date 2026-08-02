<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An item with its balance.
 *
 * The balance is a sum over the ledger, not a column, so it travels beside the
 * item rather than on it - the same reason there is no cached figure anywhere in
 * this system to drift out of agreement with the rows that produced it.
 */
class StockLevelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $item = $this->resource['item'] ?? $this->resource;

        if (! $item instanceof MenuItem) {
            return [];
        }

        $onHand = round((float) ($this->resource['on_hand'] ?? 0), 3);
        $reorderPoint = $item->reorder_point === null ? null : (float) $item->reorder_point;

        return [
            'id' => (string) $item->id,
            'name' => $item->name,
            'sku' => $item->sku,
            'barcode' => $item->barcode,
            'category' => $item->menuCategory?->name,

            'unitCode' => $item->unit_code,
            'onHand' => $onHand,

            'cost' => (float) $item->cost,
            'price' => (float) $item->price,
            'value' => round($onHand * (float) $item->cost, 2),

            'reorderPoint' => $reorderPoint,
            'belowReorderPoint' => $reorderPoint !== null && $onHand <= $reorderPoint,

            // Always a data problem - an unrecorded receipt, a double-posted
            // sale - and worth flagging because every valuation on top of it is
            // wrong.
            'negative' => $onHand < 0,

            'tracksStock' => (bool) $item->track_stock,
            'tracksSerials' => (bool) $item->track_serials,
        ];
    }
}
