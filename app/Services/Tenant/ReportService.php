<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Transfer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * `count`/`total`/`average` are always computed server-side from the same
 * filtered set (never trusts a client-sent aggregate) — via SQL aggregates
 * rather than pulling every row into a Collection just to count/sum it.
 * Neither reports.index nor reports.pdf reference lineItems/bank/changeBank
 * on a transfer, so those relations are deliberately not eager-loaded here.
 */
class ReportService
{
    public function generate(string $from, string $to): array
    {
        $count = $this->scoped($from, $to)->count();
        $total = (float) $this->scoped($from, $to)->sum('amount');
        $average = $count > 0 ? round($total / $count, 2) : 0.0;

        $transfers = $this->scoped($from, $to)
            ->select(['id', 'customer', 'status', 'amount', 'created_at'])
            ->oldest()
            ->get();

        return compact('transfers', 'count', 'total', 'average');
    }

    /**
     * Per-day revenue for the report's bar chart, computed with one
     * GROUP BY query instead of grouping an already-fetched Collection.
     *
     * @return Collection<string, float> keyed by "Y-m-d", sorted ascending
     */
    public function dailyTotals(string $from, string $to): Collection
    {
        return $this->scoped($from, $to)
            ->selectRaw('DATE(created_at) as day, SUM(amount) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->map(fn ($total) => (float) $total);
    }

    private function scoped(string $from, string $to): Builder
    {
        return Transfer::query()
            ->where('created_at', '>=', "{$from} 00:00:00")
            ->where('created_at', '<=', "{$to} 23:59:59");
    }
}
