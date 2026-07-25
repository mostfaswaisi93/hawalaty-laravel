<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Transfer\StoreTransferRequest;
use App\Http\Requests\Tenant\Transfer\UpdateTransferRequest;
use App\Http\Resources\Tenant\TransferResource;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Handles both /api/v1/transfers and /api/v1/history - BACKEND_PROMPT.md
 * section 7 describes History as "same as transfers with wider filters +
 * pagination", so both routes are wired to index().
 */
class TransferController extends Controller
{
    public function __construct(private readonly TransferService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $transfers = $this->service->list($request->only([
            'search', 'status', 'paymentMethod', 'from', 'to', 'per_page',
        ]));

        return TransferResource::collection($transfers);
    }

    public function store(StoreTransferRequest $request): JsonResponse
    {
        $this->authorize('create', Transfer::class);

        $transfer = $this->service->create($request->validated());

        return (new TransferResource($transfer))->response()->setStatusCode(201);
    }

    /**
     * Reading is unrestricted for any authenticated team member of the
     * tenant (see TransferPolicy's docblock), so no explicit authorize()
     * call here.
     */
    public function show(string $transfer): JsonResponse
    {
        $transferModel = $this->service->find($transfer);

        return (new TransferResource($transferModel))->response();
    }

    public function update(UpdateTransferRequest $request, string $transfer): JsonResponse
    {
        $transferModel = Transfer::findOrFail($transfer);

        $this->authorize('update', $transferModel);

        $transferModel = $this->service->updateStatus($transferModel, $request->validated());

        return (new TransferResource($transferModel))->response();
    }

    public function destroy(string $transfer): JsonResponse
    {
        $transferModel = Transfer::findOrFail($transfer);

        $this->authorize('delete', $transferModel);

        $this->service->delete($transferModel);

        return response()->json(null, 204);
    }
}
