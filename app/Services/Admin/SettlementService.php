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

/**
 * Partner clearing (المقاصة).
 *
 * WHY THIS EXISTS
 * FinanceSummaryService reports each partner's share as
 * `totalBoxBalance * percentage`, which is not what a partner is owed. Box
 * balance is already net of every withdrawal, so the moment partner A takes
 * money out, partner B's reported "share" drops too - even though B took
 * nothing. It also has no memory: there is no way to say "we settled up to
 * March, start again from April".
 *
 * THE ARITHMETIC
 *   distributable = payments - expenses          (profit earned in the period)
 *   entitlement_i = distributable * percentage_i (what partner i earned)
 *   drawn_i       = withdrawals by partner i     (what partner i already took)
 *   net_i         = entitlement_i - drawn_i      (+ we owe them, - they owe us)
 *
 * Withdrawals are deliberately absent from `distributable`: they are a
 * distribution OF profit, not a cost of earning it. Subtracting them there
 * would charge every partner for one partner's payout.
 *
 * `retained` (the business's own slice when percentages sum to under 100) is
 * computed as the RESIDUAL rather than its own percentage calculation, so
 * `sum(entitlement) + retained == distributable` holds exactly and per-line
 * rounding can never leak a stray agora. That in turn makes the reconciliation
 * identity exact:
 *
 *   sum(net) + retained == distributable - sum(drawn)
 *
 * DATE ATTRIBUTION mirrors the convention already set by
 * Admin\DashboardController::monthlyTrend(): payments and withdrawals are
 * dated by `created_at` (they have no other date), expenses by their own
 * back-datable `date` column.
 */
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

    /**
     * The live, uncommitted position for the period that is currently open.
     * This is what the clearing page shows before anyone commits to a run.
     *
     * @return array{
     *     periodFrom: Carbon, periodTo: Carbon, hasOpenDraft: bool, openDraft: ?Settlement,
     *     totalPayments: float, totalExpenses: float, distributableProfit: float,
     *     totalEntitlement: float, totalDrawn: float, totalNet: float,
     *     retained: float, percentageTotal: float, reconciles: bool,
     *     lines: array<int, array{partnerId: string, partnerName: string, percentage: float, entitlement: float, drawn: float, net: float}>,
     * }
     */
    public function preview(?Carbon $periodTo = null): array
    {
        $from = $this->nextPeriodStart();
        $to = ($periodTo ?? Carbon::today())->copy()->endOfDay();

        // The draft itself, not just whether one exists. An open draft is the
        // only thing that blocks running a new clearing, so the page has to be
        // able to send the admin TO it - saying "a draft is waiting" while
        // offering no way to reach it leaves the screen's whole purpose stuck
        // behind a message.
        $openDraft = Settlement::query()->where('status', SettlementStatus::Draft)->latest()->first();

        return $this->compute($from, $to) + [
            'openDraft' => $openDraft,
            'hasOpenDraft' => $openDraft !== null,
        ];
    }

    /**
     * Persists a clearing run. `confirm` closes the period immediately; a draft
     * leaves it open so the figures can be reviewed first.
     */
    public function create(array $data, ?string $adminId = null): Settlement
    {
        return DB::transaction(function () use ($data, $adminId) {
            // Re-derive the window inside the transaction: two admins hitting
            // "run clearing" at once must not both settle the same period.
            $from = $this->nextPeriodStart();
            $to = Carbon::parse($data['periodTo'])->endOfDay();

            if ($to->lt($from)) {
                throw new RuntimeException(__('تاريخ نهاية الفترة أقدم من بداية الفترة المفتوحة.'));
            }

            if (Settlement::query()->where('status', SettlementStatus::Draft)->exists()) {
                throw new RuntimeException(__('توجد مقاصة مسودّة بالفعل. أكّدها أو احذفها قبل بدء مقاصة جديدة.'));
            }

            $computed = $this->compute($from, $to);
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

    /** Closes a draft run, locking its period. */
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

    /**
     * Only a draft may be discarded. A confirmed run is an accounting document
     * and deleting it would silently reopen a closed period.
     */
    public function deleteDraft(Settlement $settlement): void
    {
        if ($settlement->isConfirmed()) {
            throw new RuntimeException(__('لا يمكن حذف مقاصة مؤكّدة.'));
        }

        $settlement->delete();
    }

    /**
     * The day the open period starts: the day after the last CONFIRMED run, or
     * the first money movement on record if nothing has ever been settled.
     * Drafts deliberately do not move this - an unconfirmed run has not closed
     * anything.
     */
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

    /**
     * Runs the arithmetic over a window without persisting anything.
     *
     * @return array<string, mixed>
     */
    private function compute(Carbon $from, Carbon $to): array
    {
        // HALF-OPEN window [start, end), never an inclusive BETWEEN.
        //
        // `expenses.date` is cast to `date` on the model, so Eloquent writes it
        // as "Y-m-d H:i:s". On sqlite that column is TEXT, so an inclusive
        // upper bound of "2026-01-31" compares as a STRING against the stored
        // "2026-01-31 00:00:00" and loses - silently dropping the last day of
        // every period. A half-open range is immune to that, and to the same
        // problem with sub-second precision on the two `created_at` columns.
        $windowStart = $from->copy()->startOfDay();
        $windowEnd = $to->copy()->startOfDay()->addDay();

        $totalPayments = (float) Payment::query()
            ->where('created_at', '>=', $windowStart)
            ->where('created_at', '<', $windowEnd)
            ->sum('amount');

        // Expenses carry their own back-datable date column.
        $totalExpenses = (float) Expense::query()
            ->where('date', '>=', $windowStart->toDateString())
            ->where('date', '<', $windowEnd->toDateString())
            ->sum('amount');

        $distributable = round($totalPayments - $totalExpenses, 2);

        // One grouped query rather than a withdrawal sum per partner.
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

        // Residual, not its own percentage sum - see the class docblock.
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
            // Guards the identity the page states out loud. Held by
            // construction; asserted so a future change to the rounding cannot
            // break it unnoticed.
            'reconciles' => abs(($totalNet + $retained) - ($distributable - $totalDrawn)) < 0.01,
            'lines' => $lines,
        ];
    }

    /**
     * `MQ-2026-0001`, sequential within the period's year. Runs inside
     * create()'s transaction, and `settlements.reference` is UNIQUE, so a
     * concurrent duplicate fails loudly instead of silently reusing a number.
     */
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
