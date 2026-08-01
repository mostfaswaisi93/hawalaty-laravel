<?php

namespace App\Http\Controllers\Api\V1;

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
        $results = $this->search->search((string) $request->query('q', ''), $request->user());

        return response()->json($results + [

            'itemLabel' => $this->naming->item(),
            'catalogLabel' => $this->naming->catalog(),
        ]);
    }
}
