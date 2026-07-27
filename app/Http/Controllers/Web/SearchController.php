<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Tenant\CatalogNaming;
use App\Services\Tenant\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs the header search pill (layouts/app.blade.php).
 *
 * The querying and every permission rule live in SearchService, shared with the
 * mobile API. All this adds is what only a browser needs: a `url` per result,
 * and the tenant's own word for its catalogue, because the dropdown's group
 * heading has to read "الفاكهة" for a fruit shop and not "الأصناف".
 */
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
            // The heading is data, not markup: the dropdown is rendered by
            // Alpine from this payload, so the label has to travel with it.
            'itemLabel' => $this->naming->item(),
            'catalogLabel' => $this->naming->catalog(),

            'orders' => $results['orders']->map(fn (array $o) => $o + [
                'url' => route('history.index', ['search' => $o['customer']]),
            ]),
            'customers' => $results['customers']->map(fn (array $c) => $c + [
                'url' => route('customers.show', $c['id']),
            ]),
            'banks' => $results['banks']->map(fn (array $b) => $b + [
                'url' => route('banks.index', ['search' => $b['name']]),
            ]),
            // The catalogue page has no search filter, so this uses the row's
            // own anchor - the result lands on the entry rather than at the top
            // of a page that can run to dozens of rows.
            'menuItems' => $results['menuItems']->map(fn (array $m) => $m + [
                'url' => route('menu.index').'#item-'.$m['id'],
            ]),
        ]);
    }
}
