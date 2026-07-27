<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Tenant\CatalogNaming;
use App\Services\Tenant\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global search across orders, customers, banks and catalogue entries.
 *
 * Querying and permission rules live in SearchService, shared with the web
 * header search, so the two can no longer disagree about what a member may see.
 *
 * Unlike the web version this returns NO `url` field: web routes are meaningless
 * to a mobile client, which navigates by id.
 */
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
            // The tenant's own word for a catalogue entry, so a mobile client
            // can head the group correctly without a second round trip.
            'itemLabel' => $this->naming->item(),
            'catalogLabel' => $this->naming->catalog(),
        ]);
    }
}
