<?php

namespace App\Services\Tenant;

use App\Enums\SerialStatus;
use App\Enums\StockMovementType;
use App\Models\Tenant\Account;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\SaleReturn;
use App\Models\Tenant\SaleReturnLine;
use App\Models\Tenant\SerialNumber;
use App\Models\Tenant\Transfer;
use App\Models\Tenant\TransferLineItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Goods coming back.
 *
 * Every trade needs this and no two agree on the rules, so the module separates
 * the part that is fixed from the part that is not.
 *
 * Fixed: a sale is never edited or deleted. The return is its own document
 * pointing at the original, and both stay in the ledger. Reversing by deletion
 * would make yesterday's takings depend on when you looked at them.
 *
 * Not fixed: whether the goods can be sold again. That is decided by the
 * vertical, not by the shop - once food or medicine has left the premises its
 * chain of custody is broken, and the module does not offer the choice.
 */
class ReturnService
{
    public function __construct(
        private readonly BusinessProfile $business,
        private readonly StockService $stock,
        private readonly AccountService $accounts,
        private readonly CustomerService $customers,
    ) {}

    /**
     * Whether returned goods may go back on the shelf in this trade.
     */
    public function allowsRestock(): bool
    {
        return $this->business->type()->allowsRestockOnReturn();
    }

    public function windowDays(): int
    {
        return $this->business->type()->returnWindowDays();
    }

    public function list(?string $status, ?string $search, int $perPage = 20): LengthAwarePaginator
    {
        return SaleReturn::query()
            ->with(['lines', 'transfer', 'teamMember'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->whereSearch(['reference', 'customer_name', 'customer_phone'], $search)
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * What is still returnable from a sale.
     *
     * Quantities already sent back are deducted, so two partial returns cannot
     * add up to more than was bought.
     *
     * @return Collection<int, array{line: TransferLineItem, sold: float, returned: float, remaining: float}>
     */
    public function returnableLines(Transfer $transfer): Collection
    {
        $transfer->loadMissing('lineItems.menuItem');

        $alreadyReturned = SaleReturnLine::query()
            ->whereIn('transfer_line_item_id', $transfer->lineItems->pluck('id'))
            ->whereHas('saleReturn', fn ($query) => $query->completed())
            ->groupBy('transfer_line_item_id')
            ->selectRaw('transfer_line_item_id, SUM(quantity) as qty')
            ->pluck('qty', 'transfer_line_item_id');

        return $transfer->lineItems->map(function (TransferLineItem $line) use ($alreadyReturned) {
            $sold = (float) $line->quantity;
            $returned = (float) ($alreadyReturned[$line->id] ?? 0);

            return [
                'line' => $line,
                'sold' => $sold,
                'returned' => $returned,
                'remaining' => round(max($sold - $returned, 0), 3),
            ];
        })->values();
    }

    /**
     * How many days ago the sale happened, and whether that is still inside the
     * shop's window.
     *
     * @return array{days: ?int, withinWindow: bool}
     */
    public function windowFor(?Transfer $transfer): array
    {
        $window = $this->windowDays();

        if (! $transfer instanceof Transfer || $transfer->created_at === null) {
            return ['days' => null, 'withinWindow' => $window > 0];
        }

        $days = (int) $transfer->created_at->startOfDay()->diffInDays(now()->startOfDay());

        return ['days' => $days, 'withinWindow' => $window > 0 && $days <= $window];
    }

    /**
     * Open a return as a draft. Nothing moves until it is completed.
     */
    public function create(array $data): SaleReturn
    {
        $transfer = filled($data['transferId'] ?? null) ? Transfer::find($data['transferId']) : null;

        return DB::connection('tenant')->transaction(function () use ($data, $transfer) {
            $customer = $this->customers->findOrCreate(
                $data['customerName'] ?? $transfer?->customer ?? __('عميل'),
                $data['customerPhone'] ?? null,
            );

            $return = SaleReturn::create([
                'transfer_id' => $transfer?->id,
                'customer_id' => $customer?->id,
                'reference' => $data['reference'] ?? null,
                'customer_name' => $data['customerName'] ?? $transfer?->customer ?? __('عميل'),
                'customer_phone' => $data['customerPhone'] ?? null,
                'status' => 'draft',
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'refund_method' => $data['refundMethod'] ?? 'cash',
                'account_id' => $data['accountId'] ?? null,
                'restocking_fee' => $data['restockingFee'] ?? 0,
                'team_member_id' => auth('tenant')->id(),
            ]);

            $this->replaceLines($return, $data['lines'] ?? []);

            return $return->fresh('lines');
        });
    }

    public function update(SaleReturn $return, array $data): SaleReturn
    {
        $this->assertDraft($return);

        return DB::connection('tenant')->transaction(function () use ($return, $data) {
            $return->update([
                'reason' => $data['reason'] ?? $return->reason,
                'notes' => $data['notes'] ?? $return->notes,
                'refund_method' => $data['refundMethod'] ?? $return->refund_method,
                'account_id' => $data['accountId'] ?? null,
                'restocking_fee' => $data['restockingFee'] ?? $return->restocking_fee,
            ]);

            if (array_key_exists('lines', $data)) {
                $this->replaceLines($return, $data['lines']);
            }

            return $return->fresh('lines');
        });
    }

    /**
     * Complete the return: stock back where it belongs, money back to the
     * customer.
     */
    public function complete(SaleReturn $return): SaleReturn
    {
        $this->assertDraft($return);

        $return->load(['lines.item', 'lines.serial']);

        if ($return->lines->isEmpty()) {
            throw new RuntimeException(__('لا يمكن تنفيذ مرتجع بلا بنود.'));
        }

        if ($return->refund_method === 'bank' && blank($return->account_id)) {
            throw new RuntimeException(__('حدّد الحساب الذي سيُرد منه المبلغ.'));
        }

        return DB::connection('tenant')->transaction(function () use ($return) {
            foreach ($return->lines as $line) {
                $this->restore($return, $line);
            }

            $this->refund($return);

            $return->update(['status' => 'completed', 'returned_at' => now()]);

            return $return->fresh(['lines', 'transfer']);
        });
    }

    public function reject(SaleReturn $return, ?string $reason = null): SaleReturn
    {
        $this->assertDraft($return);

        $return->update([
            'status' => 'rejected',
            'notes' => filled($reason) ? $reason : $return->notes,
        ]);

        return $return;
    }

    /**
     * @return array{count: int, refunded: float, destroyed: float}
     */
    public function summary(): array
    {
        $returns = SaleReturn::query()->completed()->with('lines.item')->get();

        return [
            'count' => $returns->count(),
            'refunded' => round((float) $returns->sum('refund_total'), 2),

            // What came back and could not be resold, at cost. The number that
            // says how much a lenient returns policy is actually costing.
            'destroyed' => round($returns->flatMap->lines
                ->filter(fn (SaleReturnLine $line) => ! $line->isResalable())
                ->sum(fn (SaleReturnLine $line) => (float) $line->quantity * (float) ($line->item?->cost ?? 0)), 2),
        ];
    }

    /**
     * Put one line back - or not.
     */
    private function restore(SaleReturn $return, SaleReturnLine $line): void
    {
        $item = $line->item;

        if (! $item instanceof MenuItem || ! $item->isStocked()) {
            return;
        }

        $attributes = [
            'reference_type' => 'sale_return',
            'reference_id' => $return->id,
            'batch_id' => $line->batch_id,
        ];

        $unit = $line->serial;

        if ($unit instanceof SerialNumber) {
            $attributes['serial_id'] = $unit->id;
            $attributes['serial_number'] = $unit->serial;
        }

        /*
         * Everything comes back onto the books first, even what is about to be
         * destroyed. Two movements rather than none: the ledger should say the
         * goods returned and then were written off, not quietly forget that the
         * customer handed something over.
         */
        $this->stock->receive(
            $item,
            (float) $line->quantity,
            (float) $item->cost,
            StockMovementType::ReturnIn,
            null,
            $attributes,
        );

        if ($line->isResalable()) {
            // Back on the shelf, but as a returned unit rather than a fresh one:
            // someone has to look at it before it is sold again.
            $unit?->update(['status' => SerialStatus::Returned]);

            return;
        }

        // Damaged, or a trade where nothing that left may come back. Either way
        // it leaves again immediately, and the waste report picks it up.
        $this->stock->issue($item, (float) $line->quantity, StockMovementType::Waste, null, array_merge($attributes, [
            'reason' => __('مرتجع غير صالح للبيع'),
        ]));

        $unit?->update(['status' => SerialStatus::Scrapped]);
    }

    /**
     * Give the money back, by whichever route was chosen.
     */
    private function refund(SaleReturn $return): void
    {
        $amount = (float) $return->refund_total;

        if ($amount <= 0) {
            return;
        }

        if ($return->refund_method === 'store_credit') {
            $customer = $return->customer;

            if ($customer !== null) {
                $customer->increment('store_credit', $amount);
            }

            return;
        }

        if ($return->refund_method !== 'bank' || blank($return->account_id)) {
            return;
        }

        // Money leaving an account is a credit, exactly as it would be for any
        // other payment out - the return is not a special kind of money.
        $this->accounts->postEntry(
            Account::findOrFail($return->account_id),
            0.0,
            $amount,
            __('مرتجع - :customer', ['customer' => $return->customer_name]),
            $return->transfer_id,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function replaceLines(SaleReturn $return, array $lines): void
    {
        $return->lines()->delete();

        $allowsRestock = $this->allowsRestock();
        $subtotal = 0.0;

        $returnable = $return->transfer instanceof Transfer
            ? $this->returnableLines($return->transfer)->keyBy(fn (array $row) => $row['line']->id)
            : collect();

        foreach ($lines as $row) {
            $originalId = $row['transferLineItemId'] ?? null;
            $original = filled($originalId) ? TransferLineItem::find($originalId) : null;
            $item = $original?->menuItem ?? MenuItem::find($row['itemId'] ?? null);

            if (! $item instanceof MenuItem) {
                continue;
            }

            $quantity = (float) ($row['quantity'] ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            // Two partial returns must not add up to more than was bought.
            if ($original !== null) {
                $remaining = (float) ($returnable[$original->id]['remaining'] ?? 0);

                if ($quantity > $remaining + 0.0005) {
                    throw new RuntimeException(__('الكمية المرتجعة من «:item» تتجاوز المتبقي (:remaining).', [
                        'item' => $item->name,
                        'remaining' => rtrim(rtrim(number_format($remaining, 3), '0'), '.'),
                    ]));
                }
            }

            $unitPrice = $original !== null
                ? (float) $original->price
                : (float) ($row['unitPrice'] ?? $item->price);

            $total = round($unitPrice * $quantity, 2);

            $return->lines()->create([
                'transfer_line_item_id' => $original?->id,
                'menu_item_id' => $item->id,
                'name' => $item->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $total,

                // The shop's opinion is overruled where the trade does not allow
                // a restock at all.
                'condition' => $allowsRestock && ($row['condition'] ?? 'resalable') === 'resalable'
                    ? 'resalable'
                    : 'damaged',

                'serial_id' => $row['serialId'] ?? null,
                'batch_id' => $row['batchId'] ?? null,
            ]);

            $subtotal += $total;
        }

        $fee = min((float) $return->restocking_fee, $subtotal);

        $return->update([
            'subtotal' => round($subtotal, 2),
            'restocking_fee' => round($fee, 2),
            'refund_total' => round($subtotal - $fee, 2),
        ]);
    }

    private function assertDraft(SaleReturn $return): void
    {
        if (! $return->isDraft()) {
            throw new RuntimeException(__('المرتجع بعد التنفيذ أو الرفض لا يُعدَّل.'));
        }
    }
}
