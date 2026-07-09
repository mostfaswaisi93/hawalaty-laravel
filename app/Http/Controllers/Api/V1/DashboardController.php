<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\TransferResource;
use App\Services\Tenant\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $service) {}

    public function stats(): JsonResponse
    {
        $stats = $this->service->stats();

        return response()->json([
            'totalRevenue' => $stats['totalRevenue'],
            'orderCount' => $stats['orderCount'],
            'recentTransfers' => TransferResource::collection($stats['recentTransfers']),
        ]);
    }

    public function weeklyActivity(): JsonResponse
    {
        return response()->json($this->service->weeklyActivity());
    }
}
