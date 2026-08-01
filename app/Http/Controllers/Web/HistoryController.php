<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Services\Tenant\TransferService;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

            'transfers' => $this->service->list($filters + ['per_page' => $perPage]),
            'filters' => $filters,
            'perPage' => $perPage,
        ]);
    }
}
