<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Partner\StorePartnerRequest;
use App\Http\Requests\Admin\Partner\UpdatePartnerRequest;
use App\Http\Resources\Admin\PartnerResource;
use App\Models\Partner;
use App\Services\Admin\PartnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PartnerController extends Controller
{
    public function __construct(private readonly PartnerService $service) {}

    public function index(): AnonymousResourceCollection
    {
        return PartnerResource::collection($this->service->list());
    }

    public function store(StorePartnerRequest $request): JsonResponse
    {
        $partner = $this->service->create($request->validated());

        return (new PartnerResource($partner))->response()->setStatusCode(201);
    }

    public function update(UpdatePartnerRequest $request, string $partner): JsonResponse
    {
        $model = Partner::findOrFail($partner);

        $model = $this->service->update($model, $request->validated());

        return (new PartnerResource($model))->response();
    }

    public function destroy(string $partner): JsonResponse
    {
        $model = Partner::findOrFail($partner);

        $this->service->delete($model);

        return response()->json(null, 204);
    }
}
