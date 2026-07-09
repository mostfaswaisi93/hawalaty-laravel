<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Package\StorePackageRequest;
use App\Http\Requests\Admin\Package\UpdatePackageRequest;
use App\Http\Resources\Admin\PackageResource;
use App\Models\Package;
use App\Services\Admin\PackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PackageController extends Controller
{
    public function __construct(private readonly PackageService $service) {}

    public function index(): AnonymousResourceCollection
    {
        return PackageResource::collection($this->service->list());
    }

    public function store(StorePackageRequest $request): JsonResponse
    {
        $package = $this->service->create($request->validated());

        return (new PackageResource($package))->response()->setStatusCode(201);
    }

    public function update(UpdatePackageRequest $request, string $package): JsonResponse
    {
        $model = Package::findOrFail($package);

        $model = $this->service->update($model, $request->validated());

        return (new PackageResource($model))->response();
    }

    public function destroy(string $package): JsonResponse
    {
        $model = Package::findOrFail($package);

        $this->service->delete($model);

        return response()->json(null, 204);
    }
}
