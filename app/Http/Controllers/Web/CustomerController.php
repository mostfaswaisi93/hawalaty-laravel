<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Tenant\CustomerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only customer/recipient directory (sidebar permission=null — any
 * authenticated member, matching History/Reports/Activity Log).
 */
class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    public function index(Request $request): View
    {
        $search = $request->query('search');

        return view('customers.index', [
            'customers' => $this->service->list($search),
            'search' => $search,
        ]);
    }

    public function show(string $customer): View
    {
        return view('customers.show', [
            'customer' => $this->service->find($customer),
        ]);
    }
}
