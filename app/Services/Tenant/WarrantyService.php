<?php

namespace App\Services\Tenant;

use App\Enums\BusinessCapability;
use App\Enums\WarrantyClaimStatus;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\SerialNumber;
use App\Models\Tenant\WarrantyClaim;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Warranty claims - the after-sale half of selling a machine.
 *
 * The commercial question behind every claim is who pays. That is decided by
 * whether the unit was still covered when the fault was reported, so the answer
 * is computed from the serial rather than typed in - and then frozen onto the
 * claim, because six months later the cover will have lapsed and the record
 * would start lying about what was decided at the time.
 */
class WarrantyService
{
    public function __construct(private readonly BusinessProfile $business) {}

    public function enabled(): bool
    {
        return $this->business->has(BusinessCapability::Warranty);
    }

    public function list(?string $status, ?string $search, int $perPage = 20): LengthAwarePaginator
    {
        return WarrantyClaim::query()
            ->with(['item', 'serialNumber', 'teamMember'])
            ->when($status === 'open', fn ($query) => $query->open())
            ->when($status && $status !== 'open', fn ($query) => $query->where('status', $status))
            ->whereSearch(['reference', 'customer_name', 'customer_phone'], $search)
            ->latest('reported_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Open a claim.
     *
     * The cover decision is taken now, from the unit's own dates, and stored.
     */
    public function open(array $data): WarrantyClaim
    {
        $unit = filled($data['serialId'] ?? null)
            ? SerialNumber::with('item')->find($data['serialId'])
            : null;

        $item = $unit?->item ?? MenuItem::find($data['itemId'] ?? null);

        if (! $item instanceof MenuItem) {
            throw new RuntimeException(__('حدّد الصنف أو الرقم التسلسلي للمطالبة.'));
        }

        return WarrantyClaim::create([
            'serial_number_id' => $unit?->id,
            'menu_item_id' => $item->id,
            'transfer_id' => $unit?->transfer_id,
            'customer_id' => $unit?->customer_id,
            'reference' => $data['reference'] ?? null,
            'customer_name' => $data['customerName'] ?? $unit?->customer?->name ?? '',
            'customer_phone' => $data['customerPhone'] ?? null,
            'issue' => $data['issue'],
            'status' => WarrantyClaimStatus::Open,

            // Decided from the unit where there is one; otherwise it is the
            // counter's judgement and they say so explicitly.
            'under_warranty' => $unit instanceof SerialNumber
                ? $unit->underWarranty()
                : (bool) ($data['underWarranty'] ?? false),

            'reported_at' => now(),
            'team_member_id' => auth('tenant')->id(),
        ]);
    }

    public function update(WarrantyClaim $claim, array $data): WarrantyClaim
    {
        $status = WarrantyClaimStatus::tryFrom((string) ($data['status'] ?? '')) ?? $claim->status;

        return DB::connection('tenant')->transaction(function () use ($claim, $data, $status) {
            $claim->update([
                'status' => $status,
                'repair_cost' => $data['repairCost'] ?? $claim->repair_cost,
                'charged_amount' => $data['chargedAmount'] ?? $claim->charged_amount,
                'resolution' => $data['resolution'] ?? $claim->resolution,

                // Stamped once, when it stops being someone's job. Reopening and
                // closing again moves it; a status change that is not a closure
                // leaves it alone.
                'resolved_at' => $status->isOpen() ? null : ($claim->resolved_at ?? now()),
            ]);

            return $claim->fresh(['item', 'serialNumber']);
        });
    }

    /**
     * @return array{open: int, inRepair: int, absorbed: float}
     */
    public function summary(): array
    {
        $claims = WarrantyClaim::query()->get(['status', 'repair_cost', 'charged_amount']);

        return [
            'open' => $claims->where('status', WarrantyClaimStatus::Open)->count(),
            'inRepair' => $claims->where('status', WarrantyClaimStatus::InRepair)->count(),

            // What the shop paid for out of its own pocket - the number that
            // tells you whether a supplier or a product line is a problem.
            'absorbed' => round($claims->sum(fn (WarrantyClaim $claim) => $claim->absorbedCost()), 2),
        ];
    }

    /**
     * Product lines generating the most claims.
     *
     * @return Collection<int, array{item: MenuItem, claims: int}>
     */
    public function worstOffenders(int $limit = 5): Collection
    {
        return WarrantyClaim::query()
            ->with('item')
            ->groupBy('menu_item_id')
            ->selectRaw('menu_item_id, COUNT(*) as total')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn (WarrantyClaim $row) => ['item' => $row->item, 'claims' => (int) $row->total])
            ->filter(fn (array $row) => $row['item'] !== null)
            ->values();
    }
}
