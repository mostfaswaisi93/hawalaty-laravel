<?php

namespace Tests\Feature\Tenant;

use App\Enums\BusinessType;
use App\Enums\StockMovementType;
use App\Enums\TransferStatus;
use App\Models\Tenant\Batch;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\BatchService;
use App\Services\Tenant\StockService;
use RuntimeException;
use Tests\TenantTestCase;

class BatchServiceTest extends TenantTestCase
{
    private function batches(): BatchService
    {
        return app(BatchService::class);
    }

    private function stock(): StockService
    {
        return app(StockService::class);
    }

    private function item(): MenuItem
    {
        return MenuItem::factory()->create([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'kind' => 'goods',
            'track_stock' => true,
            'unit_code' => 'H87',
            'cost' => 0,
        ]);
    }

    private function location(): Location
    {
        return Location::default() ?? Location::create([
            'name' => 'Test store', 'kind' => 'store', 'is_default' => true, 'active' => true,
        ]);
    }

    /**
     * Receive a quantity into a named lot.
     */
    private function receiveLot(MenuItem $item, string $number, ?string $expiry, float $qty, float $cost = 1): Batch
    {
        $batch = $this->batches()->findOrCreate($item, $number, $expiry);

        $this->stock()->receive($item, $qty, $cost, StockMovementType::Purchase, null, [
            'batch_id' => $batch->id,
            'batch_number' => $number,
            'expires_at' => $batch->expires_at,
        ]);

        return $batch->fresh();
    }

    public function test_grocery_tracks_batches_and_a_bookshop_does_not(): void
    {
        $this->assertTrue($this->batches()->enabled());

        $this->bindCurrentLicense(BusinessType::Bookstore);
        $this->assertFalse(app(BatchService::class)->enabled());
    }

    public function test_the_same_lot_number_arriving_twice_is_one_lot(): void
    {
        $item = $this->item();

        $first = $this->batches()->findOrCreate($item, 'LOT-A', '2027-01-01');
        $second = $this->batches()->findOrCreate($item, 'LOT-A', '2027-01-01');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Batch::count());
    }

    public function test_a_conflicting_expiry_is_not_silently_overwritten(): void
    {
        $item = $this->item();

        $this->batches()->findOrCreate($item, 'LOT-A', '2027-01-01');
        $again = $this->batches()->findOrCreate($item, 'LOT-A', '2030-01-01');

        // Moving an expiry is a decision, never a side effect of a delivery.
        $this->assertSame('2027-01-01', $again->expires_at->format('Y-m-d'));
    }

    public function test_a_missing_expiry_is_filled_in_when_it_arrives(): void
    {
        $item = $this->item();

        $this->batches()->findOrCreate($item, 'LOT-A');
        $again = $this->batches()->findOrCreate($item, 'LOT-A', '2027-05-05');

        $this->assertSame('2027-05-05', $again->expires_at->format('Y-m-d'));
    }

    public function test_issuing_takes_the_earliest_expiry_first(): void
    {
        $item = $this->item();
        $this->location();

        $late = $this->receiveLot($item, 'LATE', now()->addYear()->toDateString(), 10);
        $early = $this->receiveLot($item, 'EARLY', now()->addMonth()->toDateString(), 10);

        $this->stock()->issue($item, 4);

        $this->assertSame(6.0, $early->onHand());
        $this->assertSame(10.0, $late->onHand());
    }

    public function test_an_issue_spanning_two_lots_is_recorded_as_two_lines(): void
    {
        $item = $this->item();
        $this->location();

        $early = $this->receiveLot($item, 'EARLY', now()->addMonth()->toDateString(), 5);
        $late = $this->receiveLot($item, 'LATE', now()->addYear()->toDateString(), 10);

        $movements = $this->stock()->issue($item, 8);

        // Two rows, not one averaged row - a recall has to know which lot went
        // out, and a single line could not say.
        $this->assertCount(2, $movements);
        $this->assertSame(0.0, $early->onHand());
        $this->assertSame(7.0, $late->onHand());
    }

    public function test_expired_stock_is_never_picked(): void
    {
        $item = $this->item();
        $this->location();

        $expired = $this->receiveLot($item, 'OLD', now()->subDay()->toDateString(), 10);
        $good = $this->receiveLot($item, 'NEW', now()->addYear()->toDateString(), 10);

        $this->stock()->issue($item, 3);

        $this->assertSame(10.0, $expired->onHand());
        $this->assertSame(7.0, $good->onHand());
    }

    public function test_a_sale_with_only_expired_stock_records_unallocated_rather_than_dispensing_it(): void
    {
        $item = $this->item();
        $this->location();

        $expired = $this->receiveLot($item, 'OLD', now()->subDay()->toDateString(), 10);

        $movement = $this->stock()->issue($item, 3)->sole();

        // The sale is recorded, the expired lot is untouched, and the shortfall
        // is visible as an unbatched negative rather than a quiet dispense.
        $this->assertNull($movement->batch_id);
        $this->assertSame(10.0, $expired->onHand());
        $this->assertSame(7.0, $this->stock()->onHand($item->id));
    }

    public function test_undated_lots_are_picked_after_dated_ones(): void
    {
        $item = $this->item();
        $this->location();

        $undated = $this->receiveLot($item, 'NO-DATE', null, 10);
        $dated = $this->receiveLot($item, 'DATED', now()->addYear()->toDateString(), 10);

        $this->stock()->issue($item, 4);

        $this->assertSame(6.0, $dated->onHand());
        $this->assertSame(10.0, $undated->onHand());
    }

    public function test_expired_and_expiring_lists_separate_the_two(): void
    {
        $item = $this->item();
        $this->location();

        $this->receiveLot($item, 'GONE', now()->subDay()->toDateString(), 4);
        $this->receiveLot($item, 'SOON', now()->addDays(10)->toDateString(), 6);
        $this->receiveLot($item, 'FINE', now()->addYear()->toDateString(), 8);

        $this->assertSame(['GONE'], $this->batches()->expiredOnHand()->pluck('batch.batch_number')->all());
        $this->assertSame(['SOON'], $this->batches()->expiringSoon(30)->pluck('batch.batch_number')->all());
    }

    public function test_a_sold_out_lot_leaves_the_on_hand_list_but_still_exists(): void
    {
        $item = $this->item();
        $this->location();

        $batch = $this->receiveLot($item, 'LOT-A', now()->addYear()->toDateString(), 5);
        $this->stock()->issue($item, 5);

        $this->assertTrue($this->batches()->inStock()->isEmpty());

        // A recall arrives after the stock has gone; the lot has to survive it.
        $this->assertNotNull(Batch::find($batch->id));
    }

    public function test_the_recall_trace_names_who_received_the_lot(): void
    {
        $item = $this->item();
        $this->location();

        $batch = $this->receiveLot($item, 'RECALL-ME', now()->addYear()->toDateString(), 10);

        $transfer = Transfer::create([
            'customer' => 'أبو محمد',
            'status' => TransferStatus::Completed,
            'amount' => 10,
            'payment_method' => 'cash',
        ]);

        $this->stock()->issue($item, 3, StockMovementType::Sale, null, [
            'reference_type' => 'transfer',
            'reference_id' => $transfer->id,
        ]);

        $trace = $this->batches()->soldOn($batch);

        $this->assertCount(1, $trace);
        $this->assertSame('أبو محمد', $trace->first()['transfer']->customer);
        $this->assertSame(3.0, $trace->first()['quantity']);
    }

    public function test_expired_value_prices_the_problem(): void
    {
        $item = $this->item();
        $this->location();

        $this->receiveLot($item, 'GONE', now()->subDay()->toDateString(), 4, 25);

        $this->assertSame(100.0, $this->batches()->expiredValue());
    }

    public function test_writing_off_a_lot_removes_it_from_stock(): void
    {
        $item = $this->item();
        $this->location();

        $batch = $this->receiveLot($item, 'GONE', now()->subDay()->toDateString(), 4);

        $movement = $this->stock()->writeOffBatch($batch, 4, 'انتهاء الصلاحية');

        $this->assertSame('-4.000', $movement->quantity);
        $this->assertSame(StockMovementType::Waste, $movement->type);
        $this->assertSame(0.0, $batch->onHand());
        $this->assertTrue($this->batches()->expiredOnHand()->isEmpty());
    }

    public function test_a_write_off_cannot_exceed_the_lot(): void
    {
        $item = $this->item();
        $this->location();

        $batch = $this->receiveLot($item, 'LOT-A', now()->addYear()->toDateString(), 4);

        $this->expectException(RuntimeException::class);

        $this->stock()->writeOffBatch($batch, 5, 'خطأ');
    }

    public function test_a_write_off_needs_a_reason(): void
    {
        $item = $this->item();
        $this->location();

        $batch = $this->receiveLot($item, 'LOT-A', now()->addYear()->toDateString(), 4);

        $this->expectException(RuntimeException::class);

        $this->stock()->writeOffBatch($batch, 1, '  ');
    }

    public function test_a_vertical_without_batches_issues_a_single_unbatched_movement(): void
    {
        $this->bindCurrentLicense(BusinessType::Bookstore);

        $item = $this->item();
        $this->location();

        app(StockService::class)->receive($item, 10, 2);
        $movement = app(StockService::class)->issue($item, 3)->sole();

        $this->assertNull($movement->batch_id);
        $this->assertSame(0, Batch::count());
    }

    public function test_receiving_with_a_lot_number_alone_still_opens_the_lot(): void
    {
        $item = $this->item();
        $this->location();

        // No batch_id supplied - the ledger has to resolve it rather than leave a
        // number floating with no lot behind it.
        $this->stock()->receive($item, 5, 1, StockMovementType::Purchase, null, [
            'batch_number' => 'LOOSE',
            'expires_at' => now()->addYear()->toDateString(),
        ]);

        $batch = Batch::where('batch_number', 'LOOSE')->sole();

        $this->assertSame(5.0, $batch->onHand());
        $this->assertSame(1, StockMovement::whereNotNull('batch_id')->count());
    }
}
