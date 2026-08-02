<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\BatchResource;
use App\Models\Tenant\Batch;
use App\Services\Tenant\BatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BatchController extends Controller
{
    public function __construct(private readonly BatchService $batches) {}

    /**
     * Lots on hand, earliest expiry first.
     *
     * FEFO order rather than by name or by date received: the order the shelf
     * should be picked in is the order the list should read in.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return BatchResource::collection(
            $this->batches->inStock($request->query('locationId'), $request->query('search'))
        );
    }

    /**
     * The urgent lists: what has already expired, and what is about to.
     */
    public function expiring(Request $request): AnonymousResourceCollection
    {
        $days = (int) $request->query('days', Batch::SOON_DAYS);
        $location = $request->query('locationId');

        $rows = $request->boolean('expired')
            ? $this->batches->expiredOnHand($location)
            : $this->batches->expiringSoon($days > 0 ? $days : Batch::SOON_DAYS, $location);

        return BatchResource::collection($rows);
    }

    /**
     * Recall trace: which orders this lot went out on.
     *
     * Read off the ledger, because the ledger is what records the lot actually
     * picked - an order line only knows the item. This is the endpoint that gets
     * used on the worst day, so it answers with who to call, not just how many.
     */
    public function soldOn(string $batch): JsonResponse
    {
        $record = Batch::with('item')->findOrFail($batch);

        return response()->json([
            'data' => [
                'batch' => (new BatchResource(['batch' => $record, 'on_hand' => $record->onHand()]))->toArray(request()),
                'sales' => $this->batches->soldOn($record)->map(fn (array $row) => [
                    'transferId' => (string) $row['transfer']->id,
                    'customer' => $row['transfer']->customer,
                    'quantity' => $row['quantity'],
                    'at' => $row['at']?->toIso8601String(),
                ]),
            ],
        ]);
    }
}
