<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\CustomerResource;
use App\Http\Resources\Tenant\TransferResource;
use App\Services\Tenant\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly CustomerService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->query('per_page', 20) ?: 20, self::MAX_PER_PAGE);

        return CustomerResource::collection(
            $this->service->list($request->query('search'), $perPage)
        );
    }

    public function show(Request $request, string $customer): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20) ?: 20, self::MAX_PER_PAGE);

        $model = $this->service->find($customer);
        $transfers = $this->service->transfersFor($model, $perPage);

        return response()->json([
            'data' => (new CustomerResource($model))->resolve($request),
            'transfers' => TransferResource::collection($transfers)->response($request)->getData(true),
        ]);
    }
}
