<?php

namespace App\Services\Tenant;

use App\Enums\BusinessCapability;
use App\Models\Tenant\DispenseRecord;
use App\Models\Tenant\MenuItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Dispensing rules.
 *
 * The module refuses rather than warns. Handing over a prescription-only
 * medicine without a prescription is an offence committed by whoever pressed the
 * button; a system that notes it afterwards has helped rather than prevented.
 */
class PharmacyService
{
    public function __construct(private readonly BusinessProfile $business) {}

    public function enabled(): bool
    {
        return $this->business->has(BusinessCapability::Prescription);
    }

    /**
     * Refuse a sale line that is missing what the law requires with it.
     *
     * @param  array<string, mixed>  $details
     */
    public function assertDispensable(MenuItem $item, array $details): void
    {
        if (! $this->enabled() || ! $item->is_prescription) {
            return;
        }

        if (blank($details['prescriberName'] ?? null)) {
            throw new RuntimeException(__('«:item» دواء موصوف — سجّل اسم الطبيب.', ['item' => $item->name]));
        }

        if (blank($details['prescriptionNumber'] ?? null)) {
            throw new RuntimeException(__('«:item» دواء موصوف — سجّل رقم الوصفة.', ['item' => $item->name]));
        }

        // A controlled substance needs the person, not just the paper: the
        // register has to name who received it.
        if (filled($item->control_schedule) && blank($details['patientIdNumber'] ?? null)) {
            throw new RuntimeException(__('«:item» مادة خاضعة للرقابة — سجّل رقم هوية المريض.', ['item' => $item->name]));
        }
    }

    /**
     * Write the register entry.
     *
     * @param  array<string, mixed>  $details
     */
    public function record(MenuItem $item, float $quantity, array $details, ?string $transferId = null, ?string $batchId = null): DispenseRecord
    {
        return DispenseRecord::create([
            'menu_item_id' => $item->id,
            'transfer_id' => $transferId,
            'batch_id' => $batchId,
            'customer_id' => $details['customerId'] ?? null,
            'patient_name' => $details['patientName'] ?? __('عميل'),
            'patient_phone' => $details['patientPhone'] ?? null,
            'patient_id_number' => $details['patientIdNumber'] ?? null,
            'prescriber_name' => $details['prescriberName'] ?? null,
            'prescriber_licence' => $details['prescriberLicence'] ?? null,
            'prescription_number' => $details['prescriptionNumber'] ?? null,
            'prescription_date' => $details['prescriptionDate'] ?? null,
            'quantity' => $quantity,

            // Copied, not joined: reclassifying the medicine next year must not
            // rewrite what the register says was dispensed today.
            'control_schedule' => $item->control_schedule,
            'was_prescription' => (bool) $item->is_prescription,

            'notes' => $details['notes'] ?? null,
            'team_member_id' => auth('tenant')->id(),
            'dispensed_at' => now(),
        ]);
    }

    public function register(?string $from, ?string $to, ?string $search, bool $controlledOnly = true, int $perPage = 25): LengthAwarePaginator
    {
        return DispenseRecord::query()
            ->with(['item', 'teamMember', 'batch'])
            ->when($controlledOnly, fn ($query) => $query->controlled())
            ->when($from, fn ($query) => $query->whereDate('dispensed_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('dispensed_at', '<=', $to))
            ->whereSearch(['patient_name', 'prescription_number', 'prescriber_name'], $search)
            ->latest('dispensed_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Other products with the same active ingredient - the substitutes question
     * a pharmacist is asked several times a day.
     *
     * @return Collection<int, MenuItem>
     */
    public function substitutesFor(MenuItem $item): Collection
    {
        if (blank($item->active_ingredient)) {
            return collect();
        }

        return MenuItem::query()
            ->where('id', '!=', $item->id)
            ->where('active', true)
            ->where('active_ingredient', $item->active_ingredient)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, MenuItem>
     */
    public function search(?string $term): Collection
    {
        if (blank($term)) {
            return collect();
        }

        // By brand, by generic name, or by ATC code - a counter arrives at the
        // same medicine from all three.
        return MenuItem::query()
            ->where('active', true)
            ->whereSearch(['name', 'active_ingredient', 'atc_code'], $term)
            ->orderBy('name')
            ->limit(50)
            ->get();
    }

    /**
     * @return array{controlled: int, prescription: int, coldChain: int, unclassified: int}
     */
    public function summary(): array
    {
        $items = MenuItem::query()->where('kind', 'goods')->get([
            'is_prescription', 'control_schedule', 'cold_chain', 'atc_code',
        ]);

        return [
            'controlled' => $items->filter(fn (MenuItem $item) => filled($item->control_schedule))->count(),
            'prescription' => $items->where('is_prescription', true)->count(),
            'coldChain' => $items->where('cold_chain', true)->count(),
            'unclassified' => $items->filter(fn (MenuItem $item) => blank($item->atc_code))->count(),
        ];
    }
}
