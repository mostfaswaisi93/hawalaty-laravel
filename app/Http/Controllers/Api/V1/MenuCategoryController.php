<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\MenuCategory\StoreMenuCategoryRequest;
use App\Http\Resources\Tenant\MenuCategoryResource;
use App\Models\Tenant\MenuCategory;
use App\Services\Tenant\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * index/store/destroy only - no update, matching
 * ConstantsService.addMenuCategory()/removeMenuCategory() in the Angular
 * app.
 */
class MenuCategoryController extends Controller
{
    public function __construct(private readonly MenuService $service) {}

    public function index(): AnonymousResourceCollection
    {
        return MenuCategoryResource::collection($this->service->listCategories());
    }

    public function store(StoreMenuCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', MenuCategory::class);

        $category = $this->service->createCategory($request->validated());

        return (new MenuCategoryResource($category))->response()->setStatusCode(201);
    }

    public function destroy(string $menuCategory): JsonResponse
    {
        $category = MenuCategory::findOrFail($menuCategory);

        $this->authorize('delete', $category);

        $this->service->deleteCategory($category);

        return response()->json(null, 204);
    }
}
