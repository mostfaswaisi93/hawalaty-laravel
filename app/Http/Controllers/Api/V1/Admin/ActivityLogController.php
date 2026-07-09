<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/admin/activity-log — read-only, paginated, filterable by
 * adminId. Populated automatically elsewhere (TenantProvisioner,
 * LicenseService, ...) — no dedicated write Service, mirrors the tenant-side
 * App\Http\Controllers\Api\V1\ActivityLogController's rationale.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ActivityLog::query()->with('admin');

        if ($adminId = $request->query('adminId')) {
            $query->where('admin_id', $adminId);
        }

        $perPage = (int) $request->query('per_page', 20);

        $logs = $query->latest()->paginate($perPage)->withQueryString();

        return ActivityLogResource::collection($logs);
    }
}
