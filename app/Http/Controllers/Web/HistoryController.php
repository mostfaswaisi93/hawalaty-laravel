<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Tenant\TransferService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Server-rendered order history - a wider, filterable, paginated view over
 * the same TransferService::list() that backs the API. Any logged-in team
 * member may view it (no permission gate, matching the sidebar nav).
 */
class HistoryController extends Controller
{
    public function __construct(private readonly TransferService $service) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'status', 'paymentMethod', 'from', 'to']);
        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '');

        return view('history.index', [
            'transfers' => $this->service->list($filters),
            'filters' => $filters,
        ]);
    }
}
