<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Setting\UpdateSettingRequest;
use App\Http\Resources\Admin\SettingResource;
use App\Services\Admin\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SettingController extends Controller
{
    public function __construct(private readonly SettingService $service) {}

    public function index(): AnonymousResourceCollection
    {
        return SettingResource::collection($this->service->list());
    }

    /**
     * PUT /admin/settings/{key} — upserts by key, e.g. no separate create
     * endpoint is needed since a key/value store has no other identity.
     */
    public function update(UpdateSettingRequest $request, string $key): JsonResponse
    {
        $setting = $this->service->upsert($key, $request->validated()['value'] ?? null);

        return (new SettingResource($setting))->response();
    }
}
