<?php

namespace App\Services\Tenant;

use App\Enums\BusinessCapability;
use App\Enums\StockMovementType;
use App\Models\Tenant\FuelMeterReading;
use App\Models\Tenant\FuelPrice;
use App\Models\Tenant\FuelPump;
use App\Models\Tenant\FuelShift;
use App\Models\Tenant\FuelTank;
use App\Models\Tenant\FuelTankDip;
use App\Models\Tenant\MenuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Wet stock reconciliation.
 *
 * Two independent measurements of the same thing. The meters say how much was
 * pumped; the dip says how much is left. They are compared because they are
 * measured differently and will therefore disagree, and the size and direction
 * of the disagreement is the finding.
 *
 * A small variance is normal - meters have tolerances, fuel expands with heat
 * (which is why ASTM D1250 exists), and a dip stick is read by a person. A
 * consistent negative one is a leak or a theft, and both are reportable.
 */
class FuelService
{
    /**
     * Above this share of throughput a shift's variance stops being measurement
     * noise. Half a percent is the figure the trade usually investigates at.
     */
    public const VARIANCE_THRESHOLD = 0.5;

    public function __construct(private readonly StockService $stock) {}

    public function enabled(): bool
    {
        return app(BusinessProfile::class)->has(BusinessCapability::FuelStation);
    }

    /**
     * Open a shift, carrying every meter and dip forward from where the last
     * one closed. Retyping an opening reading is how a night's sales go missing.
     */
    public function openShift(array $data): FuelShift
    {
        if (FuelShift::query()->open()->exists()) {
            throw new RuntimeException(__('توجد وردية مفتوحة — أقفلها أولًا.'));
        }

        return DB::connection('tenant')->transaction(function () use ($data) {
            $shift = FuelShift::create([
                'shift_date' => $data['shiftDate'] ?? now()->toDateString(),
                'name' => $data['name'] ?? null,
                'status' => 'open',
                'opened_at' => now(),
                'team_member_id' => auth('tenant')->id(),
            ]);

            foreach (FuelPump::query()->active()->with('tank.item')->get() as $pump) {
                $shift->readings()->create([
                    'fuel_pump_id' => $pump->id,
                    'opening_reading' => $this->lastClosingReading($pump),
                    'unit_price' => $pump->tank?->item ? $this->priceFor($pump->tank->item) : 0,
                ]);
            }

            foreach (FuelTank::query()->active()->get() as $tank) {
                $shift->dips()->create([
                    'fuel_tank_id' => $tank->id,
                    'opening_dip' => $this->lastClosingDip($tank),
                ]);
            }

            return $shift->fresh(['readings.pump', 'dips.tank']);
        });
    }

    /**
     * Save the closing figures without committing to them yet.
     *
     * @param  array<string, mixed>  $readings
     * @param  array<string, mixed>  $dips
     */
    public function record(FuelShift $shift, array $readings, array $dips): FuelShift
    {
        $this->assertOpen($shift);

        DB::connection('tenant')->transaction(function () use ($shift, $readings, $dips) {
            foreach ($shift->readings as $reading) {
                $row = $readings[$reading->id] ?? null;

                if ($row === null) {
                    continue;
                }

                $reading->update([
                    'closing_reading' => filled($row['closing'] ?? null) ? (float) $row['closing'] : null,
                    'test_litres' => (float) ($row['test'] ?? 0),
                    'unit_price' => filled($row['price'] ?? null) ? (float) $row['price'] : $reading->unit_price,
                ]);
            }

            foreach ($shift->dips as $dip) {
                $row = $dips[$dip->id] ?? null;

                if ($row === null) {
                    continue;
                }

                $dip->update([
                    'closing_dip' => filled($row['closing'] ?? null) ? (float) $row['closing'] : null,
                    'deliveries' => (float) ($row['deliveries'] ?? 0),
                ]);
            }
        });

        return $shift->fresh(['readings.pump.tank', 'dips.tank']);
    }

    /**
     * Close the shift and post what the meters say was sold.
     *
     * The meters are the sales record, not the dip: a dip measures what is
     * there, and the difference between the two is exactly the variance being
     * looked for. Posting the dip instead would make the variance vanish by
     * definition and the module pointless.
     */
    public function closeShift(FuelShift $shift): FuelShift
    {
        $this->assertOpen($shift);

        $shift->load(['readings.pump.tank.item', 'dips.tank']);

        if ($shift->readings->whereNotNull('closing_reading')->isEmpty()) {
            throw new RuntimeException(__('أدخل قراءات الإقفال قبل إقفال الوردية.'));
        }

        return DB::connection('tenant')->transaction(function () use ($shift) {
            foreach ($shift->readings as $reading) {
                $litres = $reading->litresSold();
                $item = $reading->pump?->tank?->item;

                if ($litres <= 0 || ! $item instanceof MenuItem) {
                    continue;
                }

                $this->stock->issue(
                    $item,
                    $litres,
                    StockMovementType::Sale,
                    $reading->pump->tank->location_id,
                    [
                        'reference_type' => 'fuel_shift',
                        'reference_id' => $shift->id,
                        'reason' => __('مضخة :pump', ['pump' => $reading->pump->name]),
                    ],
                );
            }

            // A delivery taken mid-shift is stock in, and has to be recorded as
            // such or the tank's balance drifts by exactly that amount.
            foreach ($shift->dips as $dip) {
                $item = $dip->tank?->item;

                if ((float) $dip->deliveries <= 0 || ! $item instanceof MenuItem) {
                    continue;
                }

                $this->stock->receive(
                    $item,
                    (float) $dip->deliveries,
                    (float) $item->cost,
                    StockMovementType::Purchase,
                    $dip->tank->location_id,
                    ['reference_type' => 'fuel_shift', 'reference_id' => $shift->id],
                );
            }

            $shift->update(['status' => 'closed', 'closed_at' => now()]);

            return $shift->fresh(['readings.pump.tank', 'dips.tank']);
        });
    }

    /**
     * Meters against dips, per tank.
     *
     * @return Collection<int, array{tank: FuelTank, metered: float, dipped: ?float, variance: ?float, percent: ?float, value: float}>
     */
    public function reconcile(FuelShift $shift): Collection
    {
        $shift->loadMissing(['readings.pump.tank', 'dips.tank']);

        return $shift->dips->map(function (FuelTankDip $dip) use ($shift) {
            $metered = $shift->readings
                ->filter(fn (FuelMeterReading $reading) => $reading->pump?->fuel_tank_id === $dip->fuel_tank_id)
                ->sum(fn (FuelMeterReading $reading) => $reading->litresSold());

            $dipped = $dip->litresGone();

            // Signed deliberately: negative means more left the tank than the
            // pumps accounted for, which is the direction that matters.
            $variance = $dipped === null ? null : round($metered - $dipped, 3);

            $value = $shift->readings
                ->filter(fn (FuelMeterReading $reading) => $reading->pump?->fuel_tank_id === $dip->fuel_tank_id)
                ->sum(fn (FuelMeterReading $reading) => $reading->value());

            return [
                'tank' => $dip->tank,
                'metered' => round($metered, 3),
                'dipped' => $dipped,
                'variance' => $variance,
                'percent' => ($variance === null || $metered <= 0) ? null : round(($variance / $metered) * 100, 3),
                'value' => round($value, 2),
            ];
        })->values();
    }

    /**
     * Whether a variance is large enough to look into rather than shrug at.
     */
    public function needsInvestigation(?float $percent): bool
    {
        return $percent !== null && abs($percent) > self::VARIANCE_THRESHOLD;
    }

    /**
     * Announce a new price from a date.
     *
     * Kept as history rather than overwritten: fuel prices are set centrally and
     * change often, and an old shift has to stay readable at the price it
     * actually sold at.
     */
    public function setPrice(MenuItem $item, float $price, string $effectiveFrom): FuelPrice
    {
        $record = FuelPrice::updateOrCreate(
            ['menu_item_id' => $item->id, 'effective_from' => $effectiveFrom],
            ['price' => $price, 'team_member_id' => auth('tenant')->id()],
        );

        // The catalogue carries today's price so the till is right; the history
        // carries every price so the past stays right.
        if ($effectiveFrom <= now()->toDateString()) {
            $item->update(['price' => $price]);
        }

        return $record;
    }

    public function priceFor(MenuItem $item, ?string $on = null): float
    {
        $price = FuelPrice::query()
            ->where('menu_item_id', $item->id)
            ->whereDate('effective_from', '<=', $on ?? now()->toDateString())
            ->orderByDesc('effective_from')
            ->value('price');

        return (float) ($price ?? $item->price);
    }

    private function lastClosingReading(FuelPump $pump): float
    {
        return (float) (FuelMeterReading::query()
            ->where('fuel_pump_id', $pump->id)
            ->whereNotNull('closing_reading')
            ->latest('created_at')
            ->value('closing_reading') ?? 0);
    }

    private function lastClosingDip(FuelTank $tank): float
    {
        return (float) (FuelTankDip::query()
            ->where('fuel_tank_id', $tank->id)
            ->whereNotNull('closing_dip')
            ->latest('created_at')
            ->value('closing_dip') ?? 0);
    }

    private function assertOpen(FuelShift $shift): void
    {
        if (! $shift->isOpen()) {
            throw new RuntimeException(__('الوردية مقفلة ولا تقبل التعديل.'));
        }
    }
}
