<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\License\ChangePackageRequest;
use App\Http\Requests\Admin\License\StoreLicenseRequest;
use App\Http\Resources\Admin\LicenseResource;
use App\Http\Resources\Tenant\AuditLogResource;
use App\Http\Resources\Tenant\TeamMemberResource;
use App\Models\Admin;
use App\Models\License;
use App\Services\Admin\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LicenseController extends Controller
{
    public function __construct(private readonly LicenseService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $licenses = $this->service->list(
            $request->query('search'),
            $request->query('status'),
            $request->query('packageId'),
            (int) $request->query('per_page', 20),
        );

        return LicenseResource::collection($licenses);
    }

    public function store(StoreLicenseRequest $request): JsonResponse
    {
        $admin = $request->user();

        $license = $this->service->create($request->validated(), $admin);

        return (new LicenseResource($license->load('package')))->response()->setStatusCode(201);
    }

    public function show(string $license): JsonResponse
    {
        $model = License::findOrFail($license);

        $data = $this->service->show($model);

        return response()->json([
            'license' => new LicenseResource($data['license']),
            'team' => TeamMemberResource::collection($data['team']),
            'recentActivity' => AuditLogResource::collection($data['recentActivity']),
        ]);
    }

    public function suspend(Request $request, string $license): JsonResponse
    {
        $model = License::findOrFail($license);

        $model = $this->service->suspend($model, $request->user());

        return (new LicenseResource($model))->response();
    }

    public function activate(Request $request, string $license): JsonResponse
    {
        $model = License::findOrFail($license);

        $model = $this->service->activate($model, $request->user());

        return (new LicenseResource($model))->response();
    }

    public function changePackage(ChangePackageRequest $request, string $license): JsonResponse
    {
        $model = License::findOrFail($license);

        $model = $this->service->changePackage($model, $request->validated()['packageId'], $request->user());

        return (new LicenseResource($model))->response();
    }
}
