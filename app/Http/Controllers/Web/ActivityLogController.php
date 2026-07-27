<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Models\Tenant\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only activity log (append-only tenant `audit_log`). Sidebar
 * permission=null - any authenticated member. Mirrors the API
 * ActivityLogController's search + category filtering; no writes.
 */
class ActivityLogController extends Controller
{
    use HasPerPage;

    public function index(Request $request): View
    {
        // 20 by default rather than the admin tables' 10: this is a scan-heavy
        // audit trail, and the whitelist keeps a crafted per_page bounded.
        $perPage = $this->perPage($request, 20);

        $filters = array_filter([
            'search' => $request->query('search'),
            'category' => $request->query('category'),
        ], fn ($value) => $value !== null && $value !== '');

        $query = AuditLog::query();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('actor_name', 'like', "%{$search}%")
                    ->orWhere('actor_email', 'like', "%{$search}%")
                    ->orWhere('entity_label', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        return view('activity-log.index', [
            'logs' => $query->latest()->paginate($perPage)->withQueryString(),
            'filters' => $filters,
            'perPage' => $perPage,
        ]);
    }
}
