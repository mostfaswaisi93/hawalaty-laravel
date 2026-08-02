<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\SaleReturnResource;
use App\Models\Tenant\SaleReturn;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read access to returns.
 *
 * Creating one stays on the web. A return moves stock, moves money and, in food
 * and medicine, destroys goods - all inside one transaction with rules that
 * differ by vertical. A second entry point into that would be a second place for
 * those rules to be got wrong.
 */
class ReturnController extends Controller
{
    public function __construct(private readonly ReturnService $returns) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return SaleReturnResource::collection(
            $this->returns->list($request->query('status'), $request->query('search'), 20)
        );
    }

    public function show(string $saleReturn): JsonResponse
    {
        $record = SaleReturn::with(['lines.item', 'transfer'])->findOrFail($saleReturn);

        return (new SaleReturnResource($record))->response();
    }

    /**
     * What of a sale is still returnable, and under which rules.
     *
     * Answered before anything is entered so a client can grey out what it must
     * not offer - notably the restock choice, which food and medicine do not get
     * to make.
     */
    public function policy(string $transfer): JsonResponse
    {
        $sale = Transfer::with('lineItems.menuItem')->findOrFail($transfer);

        return response()->json([
            'data' => [
                'allowsRestock' => $this->returns->allowsRestock(),
                'windowDays' => $this->returns->windowDays(),
                'window' => $this->returns->windowFor($sale),

                'lines' => $this->returns->returnableLines($sale)->map(fn (array $row) => [
                    'transferLineItemId' => (string) $row['line']->id,
                    'itemId' => $row['line']->menu_item_id,
                    'name' => $row['line']->name,
                    'sold' => $row['sold'],
                    'returned' => $row['returned'],

                    // Two partial returns must not add up to more than was
                    // bought, so the remainder is what a client may offer.
                    'remaining' => $row['remaining'],

                    'unitPrice' => $row['line']->unitPrice(),
                ]),
            ],
        ]);
    }
}
