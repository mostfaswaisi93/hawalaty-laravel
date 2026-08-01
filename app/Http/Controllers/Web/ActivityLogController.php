<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Models\Tenant\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    use HasPerPage;

    public function index(Request $request): View
    {
        $perPage = $this->perPage($request, 20);

        $filters = array_filter([
            'search' => $request->query('search'),
            'category' => $request->query('category'),
        ], fn ($value) => $value !== null && $value !== '');

        $query = AuditLog::query();

        $query->whereSearch(['actor_name', 'actor_email', 'entity_label'], $filters['search'] ?? null);

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
