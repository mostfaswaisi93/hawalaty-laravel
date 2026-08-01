<?php

namespace App\Services\Admin;

use App\Enums\SettlementStatus;
use App\Models\Expense;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Settlement;
use App\Models\Withdrawal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SettlementService
{
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Settlement::query()
            ->with('admin')
            ->withCount('lines')
            ->latest('period_to')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function preview(?Carbon $periodTo = null): array
    {
        $from = $this->nextPeriodStart();
        $to = ($periodTo ?? Carbon::today())->copy()->endOfDay();

        $openDraft = Settlement::query()->where('status', SettlementStatus::Draft)->latest()->first();

        return $this->compute($from, $to) + [
            'openDraft' => $openDraft,
            'hasOpenDraft' => $openDraft !== null,
        ];
    }

    public function create(array $data, ?string $adminId = null): Settlement
    {
        return DB::transaction(function () use ($data, $adminId) {
            $from = $this->nextPeriodStart();
            $to = Carbon::parse($data['periodTo'])->endOfDay();

            if ($to->lt($from)) {
                throw new RuntimeException(__('تاريخ نهاية الفترة أقدم من بداية الفترة المفتوحة.'));
            }

            if (Settlement::query()->where('status', SettlementStatus::Draft)->exists()) {
                throw new RuntimeException(__('توجد مقاصة مسودّة بالفعل. أكّدها أو احذفها قبل بدء مقاصة جديدة.'));
            }

            $computed = $this->compute($from, $to);

            if ($computed['percentageTotal'] > 100) {
                throw new RuntimeException(__('مجموع نسب الشركاء :total% يتجاوز 100% - راجع نسب الشركاء قبل تنفيذ المقاصة.', [
                    'total' => number_format($computed['percentageTotal'], 2),
                ]));
            }

            $confirming = (bool) ($data['confirm'] ?? false);

            $settlement = Settlement::create([
                'reference' => $this->nextReference($to),
                'period_from' => $from->toDateString(),
                'period_to' => $to->toDateString(),
                'total_payments' => $computed['totalPayments'],
                'total_expenses' => $computed['totalExpenses'],
                'distributable_profit' => $computed['distributableProfit'],
                'total_entitlement' => $computed['totalEntitlement'],
                'total_drawn' => $computed['totalDrawn'],
                'total_net' => $computed['totalNet'],
                'retained' => $computed['retained'],
                'status' => $confirming ? SettlementStatus::Confirmed : SettlementStatus::Draft,
                'admin_id' => $adminId,
                'notes' => $data['notes'] ?? null,
                'confirmed_at' => $confirming ? now() : null,
            ]);

            foreach ($computed['lines'] as $line) {
                $settlement->lines()->create([
                    'partner_id' => $line['partnerId'],
                    'partner_name' => $line['partnerName'],
                    'percentage' => $line['percentage'],
                    'entitlement' => $line['entitlement'],
                    'drawn' => $line['drawn'],
                    'net' => $line['net'],
                ]);
            }

            return $settlement->load('lines');
        });
    }

    public function confirm(Settlement $settlement): Settlement
    {
        if ($settlement->isConfirmed()) {
            throw new RuntimeException(__('هذه المقاصة مؤكّدة بالفعل.'));
        }

        $settlement->update([
            'status' => SettlementStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        return $settlement;
    }

    public function deleteDraft(Settlement $settlement): void
    {
        if ($settlement->isConfirmed()) {
            throw new RuntimeException(__('لا يمكن حذف مقاصة مؤكّدة.'));
        }

        $settlement->delete();
    }

    public function nextPeriodStart(): Carbon
    {
        $last = Settlement::query()
            ->where('status', SettlementStatus::Confirmed)
            ->orderByDesc('period_to')
            ->first();

        if ($last) {
            return $last->period_to->copy()->addDay()->startOfDay();
        }

        $earliest = collect([
            Payment::query()->min('created_at'),
            Expense::query()->min('date'),
            Withdrawal::query()->min('created_at'),
        ])->filter()->map(fn ($value) => Carbon::parse($value))->min();

        return ($earliest ?? Carbon::today())->copy()->startOfDay();
    }

    private function compute(Carbon $from, Carbon $to): array
    {
        $windowStart = $from->copy()->startOfDay();
        $windowEnd = $to->copy()->startOfDay()->addDay();

        $totalPayments = (float) Payment::query()
            ->where('created_at', '>=', $windowStart)
            ->where('created_at', '<', $windowEnd)
            ->sum('amount');

        $totalExpenses = (float) Expense::query()
            ->where('date', '>=', $windowStart->toDateString())
            ->where('date', '<', $windowEnd->toDateString())
            ->sum('amount');

        $distributable = round($totalPayments - $totalExpenses, 2);

        $drawnByPartner = Withdrawal::query()
            ->where('created_at', '>=', $windowStart)
            ->where('created_at', '<', $windowEnd)
            ->groupBy('partner_id')
            ->selectRaw('partner_id, SUM(amount) as total')
            ->pluck('total', 'partner_id');

        $partners = Partner::query()->orderBy('name')->get();

        $lines = [];
        $totalEntitlement = 0.0;
        $totalDrawn = 0.0;

        foreach ($partners as $partner) {
            $percentage = (float) $partner->percentage;
            $entitlement = round($distributable * $percentage / 100, 2);
            $drawn = round((float) ($drawnByPartner[$partner->id] ?? 0), 2);

            $lines[] = [
                'partnerId' => (string) $partner->id,
                'partnerName' => $partner->name,
                'percentage' => $percentage,
                'entitlement' => $entitlement,
                'drawn' => $drawn,
                'net' => round($entitlement - $drawn, 2),
            ];

            $totalEntitlement = round($totalEntitlement + $entitlement, 2);
            $totalDrawn = round($totalDrawn + $drawn, 2);
        }

        $retained = round($distributable - $totalEntitlement, 2);
        $totalNet = round($totalEntitlement - $totalDrawn, 2);

        return [
            'periodFrom' => $from->copy(),
            'periodTo' => $to->copy(),
            'totalPayments' => round($totalPayments, 2),
            'totalExpenses' => round($totalExpenses, 2),
            'distributableProfit' => $distributable,
            'totalEntitlement' => $totalEntitlement,
            'totalDrawn' => $totalDrawn,
            'totalNet' => $totalNet,
            'retained' => $retained,
            'percentageTotal' => round($partners->sum(fn (Partner $p) => (float) $p->percentage), 2),

            'reconciles' => abs(($totalNet + $retained) - ($distributable - $totalDrawn)) < 0.01,
            'lines' => $lines,
        ];
    }

    private function nextReference(Carbon $periodTo): string
    {
        $year = $periodTo->year;
        $prefix = "MQ-{$year}-";

        $last = Settlement::query()
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->value('reference');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
