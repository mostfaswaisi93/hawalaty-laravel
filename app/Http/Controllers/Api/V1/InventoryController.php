<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\StockLevelResource;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\StockMovement;
use App\Services\Tenant\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read access to the stock ledger.
 *
 * Read only, deliberately. Every write to this ledger has to happen inside the
 * transaction that caused it - a sale, a receipt, a count - and those already
 * have endpoints. An API that could post a bare movement would be an API that
 * could invent stock, and the negative-balance report would be the first thing
 * to notice.
 */
class InventoryController extends Controller
{
    public function __construct(private readonly StockService $stock) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $items = MenuItem::query()
            ->stocked()
            ->with('menuCategory')
            ->whereSearch(['name', 'sku', 'barcode'], $request->query('search'))
            ->orderBy('name')
            ->get();

        $location = $request->query('locationId');
        $onHand = $this->stock->onHandFor($items->pluck('id')->all(), $location);

        $rows = $items->map(fn (MenuItem $item) => [
            'item' => $item,
            'on_hand' => $onHand[$item->id] ?? 0.0,
        ]);

        // Filters applied after the balance is known, because "below the reorder
        // point" is a fact about the ledger and not about the item row.
        if ($request->boolean('belowReorderPoint')) {
            $rows = $rows->filter(fn (array $row) => $row['item']->reorder_point !== null
                && $row['on_hand'] <= (float) $row['item']->reorder_point);
        }

        if ($request->boolean('negative')) {
            $rows = $rows->filter(fn (array $row) => $row['on_hand'] < 0);
        }

        return StockLevelResource::collection($rows->values());
    }

    public function show(Request $request, string $item): JsonResponse
    {
        $record = MenuItem::with('menuCategory')->findOrFail($item);
        $location = $request->query('locationId');

        $movements = StockMovement::query()
            ->forItem($record->id)
            ->atLocation($location)
            ->with(['location', 'teamMember'])
            ->latest()
            ->limit(100)
            ->get();

        return response()->json([
            'data' => array_merge(
                (new StockLevelResource([
                    'item' => $record,
                    'on_hand' => $this->stock->onHand($record->id, $location),
                ]))->toArray($request),
                [
                    'movements' => $movements->map(fn (StockMovement $movement) => [
                        'id' => (string) $movement->id,
                        'type' => $movement->type->value,
                        'typeLabel' => $movement->type->label(),
                        'quantity' => (float) $movement->quantity,

                        // The cost as it stood when the movement was written, not
                        // as it stands now.
                        'unitCost' => (float) $movement->unit_cost,

                        'batchNumber' => $movement->batch_number,
                        'serialNumber' => $movement->serial_number,
                        'location' => $movement->location?->name,
                        'reason' => $movement->reason,
                        'by' => $movement->teamMember?->name,
                        'at' => $movement->created_at?->toIso8601String(),
                    ]),
                ],
            ),
        ]);
    }
}
