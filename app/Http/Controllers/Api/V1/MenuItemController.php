<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\MenuItem\StoreMenuItemRequest;
use App\Http\Requests\Tenant\MenuItem\UpdateMenuItemRequest;
use App\Http\Resources\Tenant\MenuItemResource;
use App\Models\Tenant\MenuItem;
use App\Services\Tenant\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MenuItemController extends Controller
{
    public function __construct(private readonly MenuService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return MenuItemResource::collection($this->service->listItems($request->query('search')));
    }

    public function store(StoreMenuItemRequest $request): JsonResponse
    {
        $this->authorize('create', MenuItem::class);

        $item = $this->service->createItem($request->validated());

        return (new MenuItemResource($item))->response()->setStatusCode(201);
    }

    public function update(UpdateMenuItemRequest $request, string $menuItem): JsonResponse
    {
        $item = MenuItem::findOrFail($menuItem);

        $this->authorize('update', $item);

        $item = $this->service->updateItem($item, $request->validated());

        return (new MenuItemResource($item))->response();
    }

    public function destroy(string $menuItem): JsonResponse
    {
        $item = MenuItem::findOrFail($menuItem);

        $this->authorize('delete', $item);

        $this->service->deleteItem($item);

        return response()->json(null, 204);
    }
}
