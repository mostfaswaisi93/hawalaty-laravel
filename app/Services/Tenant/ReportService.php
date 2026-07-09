<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Transfer;

/**
 * `count`/`total`/`average` are always computed server-side from the same
 * filtered set (never trusts a client-sent aggregate).
 */
class ReportService
{
    public function generate(string $from, string $to): array
    {
        $transfers = Transfer::with(['lineItems', 'bank', 'changeBank'])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->oldest()
            ->get();

        $count = $transfers->count();
        $total = (float) $transfers->sum('amount');
        $average = $count > 0 ? round($total / $count, 2) : 0.0;

        return compact('transfers', 'count', 'total', 'average');
    }
}
