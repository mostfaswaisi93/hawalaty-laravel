<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only platform audit trail. Populated automatically by the admin
 * services (LicenseService, TenantProvisioner, ...) — there is no write path
 * here.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = ActivityLog::query()->with('admin');

        if ($adminId = $request->query('adminId')) {
            $query->where('admin_id', $adminId);
        }

        return view('admin.logs.index', [
            'logs' => $query->latest()->paginate(30),
            'admins' => Admin::query()->orderBy('name')->get(),
            'adminId' => $adminId,
        ]);
    }
}
