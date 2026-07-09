<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Withdrawal\StoreWithdrawalRequest;
use App\Http\Resources\Admin\WithdrawalResource;
use App\Services\Admin\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WithdrawalController extends Controller
{
    public function __construct(private readonly WithdrawalService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return WithdrawalResource::collection($this->service->list((int) $request->query('per_page', 20)));
    }

    public function store(StoreWithdrawalRequest $request): JsonResponse
    {
        $withdrawal = $this->service->create($request->validated());

        return (new WithdrawalResource($withdrawal))->response()->setStatusCode(201);
    }
}
