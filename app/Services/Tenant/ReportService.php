<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Transfer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportService
{
    public function generate(string $from, string $to): array
    {
        $summary = $this->summary($from, $to);

        $transfers = $this->transfersQuery($from, $to)->get();

        return $summary + compact('transfers');
    }

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

    public function dailyCounts(string $from, string $to): Collection
    {
        return $this->scoped($from, $to)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as orders')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('orders', 'day')
            ->map(fn ($count) => (int) $count);
    }

    public function transfersPage(string $from, string $to, int $perPage = 20): LengthAwarePaginator
    {
        return $this->transfersQuery($from, $to)->paginate($perPage)->withQueryString();
    }

    private function transfersQuery(string $from, string $to): Builder
    {
        return $this->scoped($from, $to)
            ->select(['id', 'customer', 'status', 'amount', 'created_at'])

            ->latest();
    }

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
