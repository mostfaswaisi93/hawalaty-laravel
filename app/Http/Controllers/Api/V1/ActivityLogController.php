<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\AuditLogResource;
use App\Models\Tenant\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/activity-log — read-only. `audit_log` is append-only,
 * populated automatically elsewhere (App\Services\Tenant\AuditLogger,
 * called from each domain Service) — this controller never writes to it
 * directly, so no dedicated Service class exists for it.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AuditLog::query();

        if ($search = $request->query('search')) {
            $query->where(function ($inner) use ($search) {
                $inner->where('actor_name', 'like', "%{$search}%")
                    ->orWhere('actor_email', 'like', "%{$search}%")
                    ->orWhere('entity_label', 'like', "%{$search}%");
            });
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        $perPage = (int) $request->query('per_page', 20);

        $logs = $query->latest()->paginate($perPage)->withQueryString();

        return AuditLogResource::collection($logs);
    }
}
