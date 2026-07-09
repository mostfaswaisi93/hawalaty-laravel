<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ConstantList\StoreConstantListItemRequest;
use App\Http\Requests\Tenant\ConstantList\StoreConstantListRequest;
use App\Http\Resources\Tenant\ConstantListResource;
use App\Models\Tenant\ConstantList;
use App\Services\Tenant\ConstantListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConstantListController extends Controller
{
    public function __construct(private readonly ConstantListService $service) {}

    public function index(): AnonymousResourceCollection
    {
        return ConstantListResource::collection($this->service->list());
    }

    public function store(StoreConstantListRequest $request): JsonResponse
    {
        $this->authorize('create', ConstantList::class);

        $list = $this->service->create($request->validated());

        return (new ConstantListResource($list))->response()->setStatusCode(201);
    }

    public function destroy(string $constantList): JsonResponse
    {
        $list = ConstantList::findOrFail($constantList);

        $this->authorize('delete', $list);

        $this->service->delete($list);

        return response()->json(null, 204);
    }

    public function storeItem(StoreConstantListItemRequest $request, string $constantList): JsonResponse
    {
        $list = ConstantList::findOrFail($constantList);

        $this->authorize('update', $list);

        $list = $this->service->addItem($list, $request->validated());

        return (new ConstantListResource($list))->response()->setStatusCode(201);
    }

    public function destroyItem(string $constantList, string $item): JsonResponse
    {
        $list = ConstantList::findOrFail($constantList);

        $this->authorize('update', $list);

        $this->service->deleteItem($list, $item);

        return response()->json(null, 204);
    }
}
