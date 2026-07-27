<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Setting\UpdateSettingRequest;
use App\Http\Resources\Admin\SettingResource;
use App\Services\Admin\SettingService;
use App\Settings\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SettingController extends Controller
{
    public function __construct(
        private readonly SettingService $service,
        private readonly PlatformSettings $settings,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return SettingResource::collection($this->service->list());
    }

    /**
     * PUT /admin/settings/{key} - upserts by key; no separate create endpoint
     * is needed since a key/value store has no other identity.
     *
     * The cache flush is load-bearing: PlatformSettings caches the whole table
     * forever, so without this a value written through the API would keep
     * serving the old one to the web app until the cache happened to be
     * cleared.
     */
    public function update(UpdateSettingRequest $request, string $key): JsonResponse
    {
        $setting = $this->service->upsert($key, $request->validated()['value'] ?? null);

        $this->settings->flush();

        return (new SettingResource($setting))->response();
    }
}
