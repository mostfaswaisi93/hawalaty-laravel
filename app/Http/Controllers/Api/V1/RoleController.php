<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Role\StoreRoleRequest;
use App\Http\Requests\Tenant\Role\UpdateRoleRequest;
use App\Http\Resources\Tenant\RoleResource;
use App\Models\Tenant\Role;
use App\Services\Tenant\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $service) {}

    public function index(): AnonymousResourceCollection
    {
        return RoleResource::collection($this->service->list());
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $role = $this->service->create($request->validated());

        return (new RoleResource($role))->response()->setStatusCode(201);
    }

    public function update(UpdateRoleRequest $request, string $role): JsonResponse
    {
        $roleModel = Role::findOrFail($role);

        $this->authorize('update', $roleModel);

        $roleModel = $this->service->update($roleModel, $request->validated());

        return (new RoleResource($roleModel))->response();
    }

    public function destroy(string $role): JsonResponse
    {
        $roleModel = Role::findOrFail($role);

        $this->authorize('delete', $roleModel);

        $this->service->delete($roleModel);

        return response()->json(null, 204);
    }
}
