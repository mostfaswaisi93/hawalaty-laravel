<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\FinanceSummaryService;
use Illuminate\Http\JsonResponse;

class FinanceController extends Controller
{
    public function __construct(private readonly FinanceSummaryService $service) {}

    public function summary(): JsonResponse
    {
        return response()->json($this->service->summarize());
    }
}
