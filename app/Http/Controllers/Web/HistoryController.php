<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
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
    use HasPerPage;

    public function __construct(private readonly TransferService $service) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'status', 'paymentMethod', 'from', 'to']);
        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '');

        $perPage = $this->perPage($request, 20);

        return view('history.index', [
            // TransferService reads `per_page` (snake_case) from the filter
            // array; $perPage is already whitelisted by HasPerPage.
            'transfers' => $this->service->list($filters + ['per_page' => $perPage]),
            'filters' => $filters,
            'perPage' => $perPage,
        ]);
    }
}
