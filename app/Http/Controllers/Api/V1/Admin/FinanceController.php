<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\FinanceSummaryService;
use Illuminate\Http\JsonResponse;

class FinanceController extends Controller
{
    public function __construct(private readonly FinanceSummaryService $service) {}

    /**
     * GET /api/v1/admin/finance/summary — a computed aggregate, not a
     * single Eloquent Model/Collection, so it's returned as a plain JSON
     * array rather than wrapped in a JsonResource (BACKEND_PROMPT.md
     * section 7's "no raw Model" rule targets Controllers that would
     * otherwise return an Eloquent instance directly; this has none).
     */
    public function summary(): JsonResponse
    {
        return response()->json($this->service->summarize());
    }
}
