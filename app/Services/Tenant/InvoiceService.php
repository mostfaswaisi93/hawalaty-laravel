<?php

namespace App\Services\Tenant;

use App\Enums\InvoiceType;
use App\Enums\TaxCategory;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\SaleReturn;
use App\Models\Tenant\TenantSetting;
use App\Models\Tenant\Transfer;
use App\Support\InvoiceQr;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Issuing invoices to EN 16931.
 *
 * Three things this gets right because they are the three that get audited:
 *
 *  - Numbering has no gaps. A counter row is locked while a number is taken,
 *    rather than reading max + 1, which under two concurrent tills produces
 *    either a duplicate or a hole and both are findings.
 *
 *  - Tax is computed per line and reported per category. Shelf prices here
 *    include tax, and the standard wants line amounts net of it, so the net is
 *    derived rather than stored twice and allowed to disagree.
 *
 *  - Each document hashes the one before it. Editing an old invoice breaks
 *    every hash after it, which is why nothing in the application offers to.
 *
 * What it does not do: sign with a tax-authority certificate or submit for
 * clearance. Those need credentials and a per-country integration, and claiming
 * them without doing them would be worse than leaving them out.
 */
class InvoiceService
{
    public const SELLER_NAME = 'invoice_seller_name';

    public const SELLER_TAX_NUMBER = 'invoice_seller_tax_number';

    public const SELLER_ADDRESS = 'invoice_seller_address';

    public const CURRENCY = 'invoice_currency';

    public function __construct(private readonly UblRenderer $ubl) {}

    /**
     * @return array<string, ?string>
     */
    public function sellerSettings(): array
    {
        $stored = TenantSetting::query()
            ->whereIn('key', [self::SELLER_NAME, self::SELLER_TAX_NUMBER, self::SELLER_ADDRESS, self::CURRENCY])
            ->pluck('value', 'key');

        return [
            self::SELLER_NAME => $stored[self::SELLER_NAME] ?? null,
            self::SELLER_TAX_NUMBER => $stored[self::SELLER_TAX_NUMBER] ?? null,
            self::SELLER_ADDRESS => $stored[self::SELLER_ADDRESS] ?? null,
            self::CURRENCY => $stored[self::CURRENCY] ?? 'ILS',
        ];
    }

    /**
     * @param  array<string, ?string>  $values
     */
    public function saveSellerSettings(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! in_array($key, [self::SELLER_NAME, self::SELLER_TAX_NUMBER, self::SELLER_ADDRESS, self::CURRENCY], true)) {
                continue;
            }

            TenantSetting::updateOrCreate(['key' => $key], ['value' => filled($value) ? trim((string) $value) : null]);
        }
    }

    /**
     * Whether the seller is identified well enough to issue anything.
     *
     * A tax number is not required for a simplified over-the-counter invoice,
     * but a name is - an invoice that cannot say who issued it is not one.
     */
    public function isConfigured(): bool
    {
        return filled($this->sellerSettings()[self::SELLER_NAME]);
    }

    public function list(?string $type, ?string $search, int $perPage = 20): LengthAwarePaginator
    {
        return Invoice::query()
            ->with('lines')
            ->when($type, fn ($query) => $query->where('type', $type))
            ->whereSearch(['number', 'buyer_name', 'buyer_tax_number'], $search)
            ->orderByDesc('issued_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Issue an invoice for a sale.
     *
     * @param  array<string, mixed>  $buyer
     */
    public function issueForSale(Transfer $transfer, array $buyer = []): Invoice
    {
        if (Invoice::where('transfer_id', $transfer->id)->exists()) {
            throw new RuntimeException(__('صدرت فاتورة لهذه البيعة مسبقًا.'));
        }

        $transfer->loadMissing(['lineItems.menuItem', 'lineItems.modifiers']);

        if ($transfer->lineItems->isEmpty()) {
            throw new RuntimeException(__('لا يمكن إصدار فاتورة لبيعة بلا بنود.'));
        }

        $lines = $transfer->lineItems->values()->map(fn ($line, $index) => [
            'position' => $index + 1,
            'item' => $line->menuItem,
            'name' => $line->name,
            'quantity' => (float) $line->quantity,

            // The option a customer chose is part of what they paid for, so it
            // belongs in the unit price rather than as an invisible extra.
            'gross_unit' => $line->unitPrice(),
        ]);

        // Type follows the buyer: a business customer with a tax number needs a
        // full tax invoice, an over-the-counter sale a simplified one.
        $type = filled($buyer['taxNumber'] ?? null) ? InvoiceType::Standard : InvoiceType::Simplified;

        return $this->issue(
            $type,
            $lines,
            (float) $transfer->discount,
            array_merge(['name' => $transfer->customer], $buyer),
            ['transfer_id' => $transfer->id],
        );
    }

    /**
     * Issue a credit note for a completed return.
     *
     * A separate document, never a reversal of the original: the invoice that
     * was issued stays issued, and the credit note says how much of it was
     * given back.
     */
    public function issueForReturn(SaleReturn $saleReturn): Invoice
    {
        if (! $saleReturn->isCompleted()) {
            throw new RuntimeException(__('لا يُصدر إشعار دائن إلا لمرتجع منفَّذ.'));
        }

        if (Invoice::where('sale_return_id', $saleReturn->id)->exists()) {
            throw new RuntimeException(__('صدر إشعار دائن لهذا المرتجع مسبقًا.'));
        }

        $saleReturn->loadMissing(['lines.item', 'transfer']);

        $lines = $saleReturn->lines->values()->map(fn ($line, $index) => [
            'position' => $index + 1,
            'item' => $line->item,
            'name' => $line->name,
            'quantity' => (float) $line->quantity,
            'gross_unit' => (float) $line->unit_price,
        ]);

        return $this->issue(
            InvoiceType::CreditNote,
            $lines,
            (float) $saleReturn->restocking_fee,
            ['name' => $saleReturn->customer_name],
            ['sale_return_id' => $saleReturn->id],
        );
    }

    /**
     * Verify the chain end to end.
     *
     * Each document stores the hash of the one before it in its series, so a
     * single edit shows up as the first mismatch and everything after it.
     *
     * Per series rather than one global chain, because the series row is what
     * gets locked while a number is taken: chaining inside it is serialised by
     * that lock, whereas a global chain would let two concurrent tills read the
     * same tail and both claim to follow it.
     *
     * @return Collection<int, array{invoice: Invoice, valid: bool}>
     */
    public function verifyChain(): Collection
    {
        return Invoice::query()
            ->chronological()
            ->get()
            ->groupBy('series')
            ->map(function (Collection $series) {
                $expected = null;

                return $series->map(function (Invoice $invoice) use (&$expected) {
                    $valid = $invoice->previous_hash === $expected
                        && $invoice->hash === $this->hash($invoice->previous_hash, (string) $invoice->xml);

                    $expected = $invoice->hash;

                    return ['invoice' => $invoice, 'valid' => $valid];
                });
            })
            ->flatten(1)
            ->values();
    }

    /**
     * @return array{count: int, net: float, tax: float, gross: float}
     */
    public function summary(): array
    {
        $invoices = Invoice::query()->get(['type', 'tax_exclusive_amount', 'tax_amount', 'payable_amount']);

        // Credit notes count against the totals, or the figures would show what
        // was billed rather than what was earned.
        $sign = fn (Invoice $invoice) => $invoice->isCreditNote() ? -1 : 1;

        return [
            'count' => $invoices->count(),
            'net' => round($invoices->sum(fn ($i) => $sign($i) * (float) $i->tax_exclusive_amount), 2),
            'tax' => round($invoices->sum(fn ($i) => $sign($i) * (float) $i->tax_amount), 2),
            'gross' => round($invoices->sum(fn ($i) => $sign($i) * (float) $i->payable_amount), 2),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     * @param  array<string, mixed>  $buyer
     * @param  array<string, mixed>  $links
     */
    private function issue(InvoiceType $type, Collection $lines, float $discount, array $buyer, array $links): Invoice
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(__('أدخل بيانات البائع قبل إصدار أي فاتورة.'));
        }

        $seller = $this->sellerSettings();

        return DB::connection('tenant')->transaction(function () use ($type, $lines, $discount, $buyer, $links, $seller) {
            [$series, $sequence] = $this->nextNumber($type);

            $computed = $this->computeLines($lines, $discount);

            // The tail of this series. Safe to read unlocked only because the
            // sequence row above is held for the rest of the transaction, so
            // nothing else can be appending to the same series right now.
            $previous = Invoice::query()->where('series', $series)->orderByDesc('sequence')->first();

            $invoice = Invoice::create(array_merge($links, [
                'series' => $series,
                'sequence' => $sequence,
                'number' => $series.'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
                'type' => $type,
                'issued_at' => now(),
                'currency_code' => $seller[self::CURRENCY] ?: 'ILS',

                'seller_name' => $seller[self::SELLER_NAME],
                'seller_tax_number' => $seller[self::SELLER_TAX_NUMBER],
                'seller_address' => $seller[self::SELLER_ADDRESS],

                'buyer_name' => $buyer['name'] ?? null,
                'buyer_tax_number' => $buyer['taxNumber'] ?? null,
                'buyer_address' => $buyer['address'] ?? null,

                'line_extension_amount' => $computed['lineExtension'],
                'allowance_total' => $computed['allowance'],
                'tax_exclusive_amount' => $computed['taxExclusive'],
                'tax_amount' => $computed['tax'],
                'tax_inclusive_amount' => $computed['taxInclusive'],
                'payable_amount' => $computed['taxInclusive'],

                'previous_hash' => $previous?->hash,

                // Placeholder: the real hash covers the XML, which cannot be
                // rendered until the lines exist.
                'hash' => '',
                'team_member_id' => auth('tenant')->id(),
            ]));

            foreach ($computed['lines'] as $line) {
                $invoice->lines()->create($line);
            }

            $invoice->load('lines');

            $xml = $this->ubl->render($invoice);

            $invoice->update([
                'xml' => $xml,
                'hash' => $this->hash($previous?->hash, $xml),
                'qr_payload' => InvoiceQr::payload(
                    (string) $invoice->seller_name,
                    (string) $invoice->seller_tax_number,
                    $invoice->issued_at->toIso8601String(),
                    number_format((float) $invoice->tax_inclusive_amount, 2, '.', ''),
                    number_format((float) $invoice->tax_amount, 2, '.', ''),
                ),
            ]);

            return $invoice->refresh()->load('lines');
        });
    }

    /**
     * Take the next number under a lock.
     *
     * @return array{0: string, 1: int}
     */
    private function nextNumber(InvoiceType $type): array
    {
        $series = $type->seriesPrefix().'-'.now()->format('Y');

        $connection = DB::connection('tenant');

        // Created first so there is always a row to lock; the lock is what makes
        // two concurrent tills take different numbers.
        $connection->table('invoice_sequences')->insertOrIgnore([
            'series' => $series,
            'next_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $current = (int) $connection->table('invoice_sequences')
            ->where('series', $series)
            ->lockForUpdate()
            ->value('next_number');

        $connection->table('invoice_sequences')
            ->where('series', $series)
            ->update(['next_number' => $current + 1, 'updated_at' => now()]);

        return [$series, $current];
    }

    /**
     * Turn what the till charged into the net amounts EN 16931 wants, and
     * spread any document discount across the lines.
     *
     * Two decisions worth naming:
     *
     * The discount is allocated proportionally into the lines rather than shown
     * only at document level. The tax breakdown has to reflect it - a discount
     * that reduces what was paid but not what was taxed overstates the tax due -
     * and once it is inside the lines the document-level allowance is zero and
     * LineExtension - Allowance = TaxExclusive holds without double counting.
     *
     * Net and tax are derived from one rounded figure, never rounded
     * independently, so net + tax equals the line total exactly. Rounding both
     * and hoping they meet is where a cent goes missing.
     *
     * @param  Collection<int, array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private function computeLines(Collection $lines, float $discount): array
    {
        $charged = $lines->sum(fn (array $line) => $line['gross_unit'] * $line['quantity']);
        $discount = min(max($discount, 0), $charged);
        $ratio = $charged > 0 ? ($charged - $discount) / $charged : 1.0;

        $rows = [];
        $allInclusive = true;

        foreach ($lines as $line) {
            $item = $line['item'] instanceof MenuItem ? $line['item'] : null;

            $rate = (float) ($item?->tax_rate ?? 0);
            $inclusive = $item === null || (bool) $item->tax_included;
            $allInclusive = $allInclusive && $inclusive;

            $category = TaxCategory::tryFrom((string) ($item?->tax_category ?? ''))
                ?? TaxCategory::inferFrom($rate);

            // A category that charges nothing must carry a zero rate, whatever
            // the catalogue happens to say.
            if (! $category->chargesTax()) {
                $rate = 0.0;
            }

            $share = $line['gross_unit'] * $line['quantity'] * $ratio;

            $rows[] = [
                'menu_item_id' => $item?->id,
                'position' => $line['position'],
                'name' => $line['name'],
                'quantity' => $line['quantity'],
                'unit_code' => $item?->unit_code ?? 'H87',
                'tax_category' => $category->value,
                'tax_rate' => $rate,
                'inclusive' => $inclusive,

                // The figure the split is derived from: for a tax-inclusive
                // price that is what the customer paid, for an exclusive one it
                // is the net and the tax is added on top.
                'anchor' => round($share, 2),
            ];
        }

        // Proportional allocation rounds each line on its own, so the parts can
        // miss the whole by a cent. Where every price included tax there is a
        // right answer to land on - what the till actually took - and the
        // residual goes on the largest line, where it is proportionally least
        // visible. Where any price excluded tax there is no such figure to match
        // and nothing is forced.
        if ($allInclusive && $rows !== []) {
            $target = round($charged - $discount, 2);
            $residual = round($target - array_sum(array_column($rows, 'anchor')), 2);

            if (abs($residual) >= 0.01) {
                $largest = 0;

                foreach ($rows as $index => $row) {
                    if ($row['anchor'] > $rows[$largest]['anchor']) {
                        $largest = $index;
                    }
                }

                $rows[$largest]['anchor'] = round($rows[$largest]['anchor'] + $residual, 2);
            }
        }

        $netTotal = 0.0;
        $taxTotal = 0.0;

        foreach ($rows as $index => $row) {
            $factor = 1 + ((float) $row['tax_rate'] / 100);

            if ($row['inclusive']) {
                $net = round($row['anchor'] / $factor, 2);
                $tax = round($row['anchor'] - $net, 2);
            } else {
                $net = $row['anchor'];
                $tax = round($net * ((float) $row['tax_rate'] / 100), 2);
            }

            $quantity = (float) $row['quantity'];

            $rows[$index]['line_extension_amount'] = $net;
            $rows[$index]['tax_amount'] = $tax;
            $rows[$index]['unit_price'] = $quantity > 0 ? round($net / $quantity, 3) : 0;

            unset($rows[$index]['anchor'], $rows[$index]['inclusive']);

            $netTotal += $net;
            $taxTotal += $tax;
        }

        // Summed from the stored lines rather than computed independently, so
        // the totals are exact by construction instead of within tolerance.
        $netTotal = round($netTotal, 2);
        $taxTotal = round($taxTotal, 2);

        return [
            'lines' => array_values($rows),
            'lineExtension' => $netTotal,
            'allowance' => 0.0,
            'taxExclusive' => $netTotal,
            'tax' => $taxTotal,
            'taxInclusive' => round($netTotal + $taxTotal, 2),
        ];
    }

    private function hash(?string $previous, string $xml): string
    {
        return hash('sha256', ($previous ?? '').$xml);
    }
}
