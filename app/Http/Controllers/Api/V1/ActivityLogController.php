<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\AuditLogResource;
use App\Models\Tenant\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AuditLog::query();

        $query->whereSearch(['actor_name', 'actor_email', 'entity_label'], $request->query('search'));

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        $perPage = (int) $request->query('per_page', 20);

        $logs = $query->latest()->paginate($perPage)->withQueryString();

        return AuditLogResource::collection($logs);
    }
}
