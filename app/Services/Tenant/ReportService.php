<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Transfer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * `count`/`total`/`average` are always computed server-side from the same
 * filtered set (never trusts a client-sent aggregate) - via SQL aggregates
 * rather than pulling every row into a Collection just to count/sum it.
 * Neither reports.index nor reports.pdf reference lineItems/bank/changeBank
 * on a transfer, so those relations are deliberately not eager-loaded here.
 */
class ReportService
{
    /**
     * The whole range, transfers included. Used by the CSV/PDF export and the
     * API, which both genuinely need every row.
     *
     * The web page must NOT use this: a busy tenant's month is thousands of
     * rows, and rendering them in one table is what `transfersPage()` exists to
     * avoid.
     */
    public function generate(string $from, string $to): array
    {
        $summary = $this->summary($from, $to);

        $transfers = $this->transfersQuery($from, $to)->get();

        return $summary + compact('transfers');
    }

    /**
     * Count/total/average only - three aggregates, no row fetching, so the
     * report page's headline figures stay correct no matter which page of the
     * table is being viewed.
     *
     * @return array{count: int, total: float, average: float}
     */
    public function summary(string $from, string $to): array
    {
        $count = $this->scoped($from, $to)->count();
        $total = (float) $this->scoped($from, $to)->sum('amount');

        return [
            'count' => $count,
            'total' => $total,
            'average' => $count > 0 ? round($total / $count, 2) : 0.0,
        ];
    }

    /** One page of the range's transfers, for the report screen's table. */
    public function transfersPage(string $from, string $to, int $perPage = 20): LengthAwarePaginator
    {
        return $this->transfersQuery($from, $to)->paginate($perPage)->withQueryString();
    }

    private function transfersQuery(string $from, string $to): Builder
    {
        return $this->scoped($from, $to)
            ->select(['id', 'customer', 'status', 'amount', 'created_at'])
            // Newest first: the table is read top-down and the export sorts
            // itself, so there is no reason to make the reader scroll to the
            // bottom for the most recent order.
            ->latest();
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
