<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Tenant\CatalogNaming;
use App\Services\Tenant\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        private readonly SearchService $search,
        private readonly CatalogNaming $naming,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $member = auth('tenant')->user();

        $results = $this->search->search($query, $member);

        return response()->json([
            'query' => $query,

            'itemLabel' => $this->naming->item(),
            'catalogLabel' => $this->naming->catalog(),

            'orders' => $results['orders']->map(fn (array $o) => $o + [
                'url' => route('history.index', ['search' => $o['customer']]),
            ]),
            'customers' => $results['customers']->map(fn (array $c) => $c + [
                'url' => route('customers.show', $c['id']),
            ]),
            'banks' => $results['banks']->map(fn (array $b) => $b + [

                'url' => route('accounts.show', $b['id']),
            ]),

            'menuItems' => $results['menuItems']->map(fn (array $m) => $m + [
                'url' => route('menu.index').'#item-'.$m['id'],
            ]),
        ]);
    }
}
