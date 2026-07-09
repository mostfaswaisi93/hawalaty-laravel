<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Transfer;
use Illuminate\Support\Carbon;

class DashboardService
{
    public function stats(): array
    {
        $recentTransfers = Transfer::with(['lineItems', 'bank', 'changeBank'])
            ->latest()
            ->take(5)
            ->get();

        return [
            'totalRevenue' => (float) Transfer::sum('amount'),
            'orderCount' => Transfer::count(),
            'recentTransfers' => $recentTransfers,
        ];
    }

    /**
     * A plain PHP loop over the last 7 days (today inclusive) with one
     * whereDate() count each, rather than a single raw SQL GROUP BY —
     * slower, but trivially correct to verify by inspection.
     */
    public function weeklyActivity(): array
    {
        $days = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);

            $days[] = [
                'date' => $date->toDateString(),
                'count' => Transfer::whereDate('created_at', $date)->count(),
            ];
        }

        return $days;
    }
}
