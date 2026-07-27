<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Services\Tenant\CustomerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only customer/recipient directory (sidebar permission=null - any
 * authenticated member, matching History/Reports/Activity Log).
 */
class CustomerController extends Controller
{
    use HasPerPage;

    public function __construct(private readonly CustomerService $service) {}

    public function index(Request $request): View
    {
        $search = $request->query('search');
        $perPage = $this->perPage($request, 20);

        return view('customers.index', [
            'customers' => $this->service->list($search, $perPage),
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    public function show(string $customer): View
    {
        return view('customers.show', [
            'customer' => $this->service->find($customer),
        ]);
    }
}
