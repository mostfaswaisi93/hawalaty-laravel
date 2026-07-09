<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Box\StoreBoxRequest;
use App\Http\Requests\Admin\Box\UpdateBoxRequest;
use App\Http\Resources\Admin\BoxResource;
use App\Models\Box;
use App\Services\Admin\BoxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BoxController extends Controller
{
    public function __construct(private readonly BoxService $service) {}

    public function index(): AnonymousResourceCollection
    {
        return BoxResource::collection($this->service->list());
    }

    public function store(StoreBoxRequest $request): JsonResponse
    {
        $box = $this->service->create($request->validated());

        return (new BoxResource($box))->response()->setStatusCode(201);
    }

    public function update(UpdateBoxRequest $request, string $box): JsonResponse
    {
        $model = Box::findOrFail($box);

        $model = $this->service->update($model, $request->validated());

        return (new BoxResource($model))->response();
    }

    public function destroy(string $box): JsonResponse
    {
        $model = Box::findOrFail($box);

        $this->service->delete($model);

        return response()->json(null, 204);
    }
}
