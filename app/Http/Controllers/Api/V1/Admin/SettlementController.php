<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settlement\StoreSettlementRequest;
use App\Http\Resources\Admin\SettlementResource;
use App\Models\Settlement;
use App\Services\Admin\SettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;

class SettlementController extends Controller
{
    public function __construct(private readonly SettlementService $service) {}

    public function index(): AnonymousResourceCollection
    {
        return SettlementResource::collection($this->service->list());
    }

    public function preview(): JsonResponse
    {
        $preview = $this->service->preview();

        return response()->json([
            'periodFrom' => $preview['periodFrom']->toDateString(),
            'periodTo' => $preview['periodTo']->toDateString(),
            'hasOpenDraft' => $preview['hasOpenDraft'],
            'totalPayments' => $preview['totalPayments'],
            'totalExpenses' => $preview['totalExpenses'],
            'distributableProfit' => $preview['distributableProfit'],
            'totalEntitlement' => $preview['totalEntitlement'],
            'totalDrawn' => $preview['totalDrawn'],
            'totalNet' => $preview['totalNet'],
            'retained' => $preview['retained'],
            'percentageTotal' => $preview['percentageTotal'],

            'reconciles' => $preview['reconciles'],
            'lines' => $preview['lines'],
        ]);
    }

    public function show(string $settlement): JsonResponse
    {
        $model = Settlement::with('lines')->findOrFail($settlement);

        return (new SettlementResource($model))->response();
    }

    public function store(StoreSettlementRequest $request): JsonResponse
    {
        try {
            $settlement = $this->service->create($request->validated(), auth()->id());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new SettlementResource($settlement->load('lines')))->response()->setStatusCode(201);
    }

    public function confirm(string $settlement): JsonResponse
    {
        $model = Settlement::findOrFail($settlement);

        try {
            $this->service->confirm($model);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new SettlementResource($model->fresh()->load('lines')))->response();
    }

    public function destroy(string $settlement): JsonResponse
    {
        $model = Settlement::findOrFail($settlement);

        try {
            $this->service->deleteDraft($model);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => __('تم حذف المسودّة.')]);
    }
}
