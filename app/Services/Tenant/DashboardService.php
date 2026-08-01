<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Transfer;
use Illuminate\Support\Carbon;

class DashboardService
{
    public function stats(): array
    {
        $recentTransfers = Transfer::with(['lineItems', 'account'])
            ->latest()
            ->take(5)
            ->get();

        return [
            'totalRevenue' => (float) Transfer::sum('amount'),
            'orderCount' => Transfer::count(),
            'recentTransfers' => $recentTransfers,
        ];
    }

    public function weeklyActivity(): array
    {
        $countsByDay = Transfer::where('created_at', '>=', Carbon::today()->subDays(6))
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $days = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i)->toDateString();

            $days[] = [
                'date' => $date,
                'count' => (int) ($countsByDay[$date] ?? 0),
            ];
        }

        return $days;
    }
}
