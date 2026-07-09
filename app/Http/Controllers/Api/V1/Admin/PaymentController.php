<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payment\StorePaymentRequest;
use App\Http\Resources\Admin\PaymentResource;
use App\Services\Admin\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return PaymentResource::collection($this->service->list((int) $request->query('per_page', 20)));
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $payment = $this->service->create($request->validated());

        return (new PaymentResource($payment))->response()->setStatusCode(201);
    }
}
