<?php

namespace App\Http\Controllers\Web;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Models\Tenant\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    use HasPerPage;

    public function index(Request $request): View
    {
        $perPage = $this->perPage($request, 20);

        // Validated, not trusted: `action`/`category` go straight into a WHERE on
        // an enum column, and a bad date would throw out of Carbon::parse.
        $valid = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', Rule::enum(AuditCategory::class)],
            'action' => ['nullable', Rule::enum(AuditAction::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $filters = array_filter($valid, fn ($value) => $value !== null && $value !== '');

        $query = AuditLog::query();

        $query->whereSearch(['actor_name', 'actor_email', 'entity_label'], $filters['search'] ?? null);

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        // Half-open upper bound: `created_at` is a timestamp, so `<= to` would
        // drop everything logged after midnight on the final day.
        if (! empty($filters['to'])) {
            $query->where('created_at', '<', Carbon::parse($filters['to'])->addDay()->startOfDay());
        }

        return view('activity-log.index', [
            'logs' => $query->latest()->paginate($perPage)->withQueryString(),
            'filters' => $filters,
            'perPage' => $perPage,
        ]);
    }
}
