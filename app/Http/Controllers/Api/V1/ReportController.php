<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Report\ReportRequest;
use App\Http\Resources\Tenant\TransferSummaryResource;
use App\Services\Tenant\ReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $service) {}

    public function index(ReportRequest $request): JsonResponse
    {
        $data = $request->validated();

        $report = $this->service->generate($data['from'], $data['to']);

        return response()->json([
            // Summary rows, matching what ReportService actually selects. The
            // full TransferResource used to be used here and 500'd on the first
            // order in range, because it reads columns the report never loads.
            'transfers' => TransferSummaryResource::collection($report['transfers']),
            'count' => $report['count'],
            'total' => $report['total'],
            'average' => $report['average'],
        ]);
    }
}
