<?php

namespace Tests\Feature\Tenant;

use App\Enums\AppointmentStatus;
use App\Enums\BusinessType;
use App\Enums\WorkOrderStatus;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\ServiceResource;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\Transfer;
use App\Models\Tenant\WorkOrder;
use App\Services\Tenant\SchedulingService;
use App\Services\Tenant\StockService;
use App\Services\Tenant\WorkOrderService;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TenantTestCase;

class SchedulingWorkOrderTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bindCurrentLicense(BusinessType::Services);
        Notification::fake();
    }

    private function scheduling(): SchedulingService
    {
        return app(SchedulingService::class);
    }

    private function service(string $name = 'صيانة', int $minutes = 60, float $price = 100): MenuItem
    {
        return MenuItem::factory()->create([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'name' => $name,
            'kind' => 'service',
            'track_stock' => false,
            'unit_code' => 'HUR',
            'service_minutes' => $minutes,
            'price' => $price,
        ]);
    }

    private function part(string $name = 'فلتر', float $price = 40): MenuItem
    {
        return MenuItem::factory()->create([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'name' => $name,
            'kind' => 'goods',
            'track_stock' => true,
            'unit_code' => 'H87',
            'price' => $price,
        ]);
    }

    private function resource(string $name = 'أحمد'): ServiceResource
    {
        return ServiceResource::create(['name' => $name, 'kind' => 'technician', 'active' => true]);
    }

    private function owner(): TeamMember
    {
        $roles = $this->seedStandardRoles();

        return TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
    }

    // -- Booking -----------------------------------------------------------

    public function test_the_service_duration_sets_the_end_time(): void
    {
        $item = $this->service(minutes: 90);
        $resource = $this->resource();

        $appointment = $this->scheduling()->book([
            'customerName' => 'أبو أحمد',
            'itemId' => $item->id,
            'resourceId' => $resource->id,
            'startsAt' => '2026-09-01 09:00',
        ]);

        $this->assertSame('10:30', $appointment->ends_at->format('H:i'));
    }

    public function test_a_resource_cannot_be_in_two_places_at_once(): void
    {
        $item = $this->service(minutes: 60);
        $resource = $this->resource();

        $this->scheduling()->book([
            'customerName' => 'الأول',
            'itemId' => $item->id,
            'resourceId' => $resource->id,
            'startsAt' => '2026-09-01 09:00',
        ]);

        $this->expectException(RuntimeException::class);

        $this->scheduling()->book([
            'customerName' => 'الثاني',
            'itemId' => $item->id,
            'resourceId' => $resource->id,
            'startsAt' => '2026-09-01 09:30',
        ]);
    }

    public function test_back_to_back_bookings_are_allowed(): void
    {
        $item = $this->service(minutes: 60);
        $resource = $this->resource();

        $this->scheduling()->book([
            'customerName' => 'الأول',
            'itemId' => $item->id,
            'resourceId' => $resource->id,
            'startsAt' => '2026-09-01 09:00',
        ]);

        // One finishing at ten and the next starting at ten is a full diary,
        // not a conflict.
        $second = $this->scheduling()->book([
            'customerName' => 'الثاني',
            'itemId' => $item->id,
            'resourceId' => $resource->id,
            'startsAt' => '2026-09-01 10:00',
        ]);

        $this->assertSame(2, Appointment::count());
        $this->assertSame('الثاني', $second->customer_name);
    }

    public function test_a_cancelled_booking_frees_its_slot(): void
    {
        $item = $this->service(minutes: 60);
        $resource = $this->resource();

        $first = $this->scheduling()->book([
            'customerName' => 'الأول',
            'itemId' => $item->id,
            'resourceId' => $resource->id,
            'startsAt' => '2026-09-01 09:00',
        ]);

        $this->scheduling()->setStatus($first, AppointmentStatus::Cancelled);

        $second = $this->scheduling()->book([
            'customerName' => 'الثاني',
            'itemId' => $item->id,
            'resourceId' => $resource->id,
            'startsAt' => '2026-09-01 09:00',
        ]);

        $this->assertSame('الثاني', $second->customer_name);
    }

    public function test_two_resources_may_work_the_same_hour(): void
    {
        $item = $this->service(minutes: 60);

        foreach (['أحمد', 'سامي'] as $name) {
            $this->scheduling()->book([
                'customerName' => 'عميل '.$name,
                'itemId' => $item->id,
                'resourceId' => $this->resource($name)->id,
                'startsAt' => '2026-09-01 09:00',
            ]);
        }

        $this->assertSame(2, Appointment::count());
    }

    public function test_rescheduling_checks_the_new_slot(): void
    {
        $item = $this->service(minutes: 60);
        $resource = $this->resource();

        $first = $this->scheduling()->book([
            'customerName' => 'الأول', 'itemId' => $item->id,
            'resourceId' => $resource->id, 'startsAt' => '2026-09-01 09:00',
        ]);

        $second = $this->scheduling()->book([
            'customerName' => 'الثاني', 'itemId' => $item->id,
            'resourceId' => $resource->id, 'startsAt' => '2026-09-01 11:00',
        ]);

        $this->expectException(RuntimeException::class);

        $this->scheduling()->reschedule($second, '2026-09-01 09:30');
    }

    public function test_a_booking_can_move_without_clashing_with_itself(): void
    {
        $item = $this->service(minutes: 60);
        $resource = $this->resource();

        $appointment = $this->scheduling()->book([
            'customerName' => 'عميل', 'itemId' => $item->id,
            'resourceId' => $resource->id, 'startsAt' => '2026-09-01 09:00',
        ]);

        $this->scheduling()->reschedule($appointment, '2026-09-01 09:15');

        $this->assertSame('09:15', $appointment->fresh()->starts_at->format('H:i'));
    }

    public function test_open_slots_are_the_gaps_between_bookings(): void
    {
        $item = $this->service(minutes: 60);
        $resource = $this->resource();

        $this->scheduling()->book([
            'customerName' => 'عميل', 'itemId' => $item->id,
            'resourceId' => $resource->id, 'startsAt' => '2026-09-01 10:00',
        ]);

        $slots = $this->scheduling()->openSlots($resource->id, '2026-09-01', 8, 12);

        $this->assertCount(2, $slots);
        $this->assertSame('08:00', $slots[0]['from']->format('H:i'));
        $this->assertSame('10:00', $slots[0]['to']->format('H:i'));
        $this->assertSame('11:00', $slots[1]['from']->format('H:i'));
    }

    // -- Work orders -------------------------------------------------------

    public function test_a_job_splits_parts_from_labour(): void
    {
        $order = app(WorkOrderService::class)->create(['customerName' => 'أبو أحمد']);

        app(WorkOrderService::class)->update($order, [
            'lines' => [
                ['itemId' => $this->part(price: 40)->id, 'quantity' => 2],
                ['itemId' => $this->service(price: 100)->id, 'quantity' => 1.5],
            ],
        ]);

        $fresh = $order->fresh();

        $this->assertSame('80.00', $fresh->parts_total);
        $this->assertSame('150.00', $fresh->labour_total);
        $this->assertSame('230.00', $fresh->total);
    }

    public function test_a_quoted_price_overrides_the_catalogue(): void
    {
        $order = app(WorkOrderService::class)->create(['customerName' => 'عميل']);

        app(WorkOrderService::class)->update($order, [
            'lines' => [['itemId' => $this->part(price: 40)->id, 'quantity' => 1, 'unitPrice' => 35]],
        ]);

        $this->assertSame('35.00', $order->fresh()->total);
    }

    /**
     * The point of routing the invoice through the till: one path for stock and
     * the ledger, not two.
     */
    public function test_invoicing_goes_through_the_ordinary_sale_path(): void
    {
        Location::default() ?? Location::create(['name' => 'Shop', 'kind' => 'store', 'is_default' => true, 'active' => true]);

        // Auto parts both schedules work and holds stock.
        $this->bindCurrentLicense(BusinessType::AutoParts);

        $part = $this->part(price: 40);
        app(StockService::class)->receive($part, 10, 25);

        $order = app(WorkOrderService::class)->create(['customerName' => 'أبو أحمد']);

        app(WorkOrderService::class)->update($order, [
            'lines' => [
                ['itemId' => $part->id, 'quantity' => 2],
                ['itemId' => $this->service(price: 100)->id, 'quantity' => 1],
            ],
        ]);

        app(WorkOrderService::class)->invoice($order->fresh(), ['paymentMethod' => 'cash']);

        $fresh = $order->fresh();

        $this->assertSame(WorkOrderStatus::Delivered, $fresh->status);
        $this->assertNotNull($fresh->transfer_id);

        // Stock left by the sale path, so the ledger agrees with the shelf.
        $this->assertSame(8.0, app(StockService::class)->onHand($part->id));
        $this->assertSame('180.00', Transfer::sole()->amount);
    }

    public function test_a_billed_job_cannot_be_edited_or_billed_again(): void
    {
        $this->bindCurrentLicense(BusinessType::AutoParts);
        Location::default() ?? Location::create(['name' => 'Shop', 'kind' => 'store', 'is_default' => true, 'active' => true]);

        $order = app(WorkOrderService::class)->create(['customerName' => 'عميل']);
        app(WorkOrderService::class)->update($order, [
            'lines' => [['itemId' => $this->service(price: 50)->id, 'quantity' => 1]],
        ]);

        app(WorkOrderService::class)->invoice($order->fresh(), ['paymentMethod' => 'cash']);

        $this->expectException(RuntimeException::class);

        app(WorkOrderService::class)->invoice($order->fresh(), ['paymentMethod' => 'cash']);
    }

    public function test_an_empty_job_cannot_be_billed(): void
    {
        $order = app(WorkOrderService::class)->create(['customerName' => 'عميل']);

        $this->expectException(RuntimeException::class);

        app(WorkOrderService::class)->invoice($order, ['paymentMethod' => 'cash']);
    }

    public function test_workmanship_warranty_runs_from_delivery(): void
    {
        $this->bindCurrentLicense(BusinessType::AutoParts);
        Location::default() ?? Location::create(['name' => 'Shop', 'kind' => 'store', 'is_default' => true, 'active' => true]);

        $order = app(WorkOrderService::class)->create([
            'customerName' => 'عميل',
            'workmanshipWarrantyDays' => 90,
        ]);

        app(WorkOrderService::class)->update($order, [
            'lines' => [['itemId' => $this->service(price: 50)->id, 'quantity' => 1]],
        ]);

        app(WorkOrderService::class)->invoice($order->fresh(), ['paymentMethod' => 'cash']);

        $fresh = $order->fresh();

        // Distinct from the manufacturer's warranty on any parts fitted.
        $this->assertTrue($fresh->workmanshipStillCovered());
        $this->assertSame(
            now()->addDays(90)->toDateString(),
            $fresh->workmanshipEndsAt()?->toDateString(),
        );
    }

    // -- Screens -----------------------------------------------------------

    public function test_the_screens_render(): void
    {
        $owner = $this->owner();
        $resource = $this->resource();
        $item = $this->service();

        $this->scheduling()->book([
            'customerName' => 'أبو أحمد', 'itemId' => $item->id,
            'resourceId' => $resource->id, 'startsAt' => '2026-09-01 09:00',
        ]);

        $order = app(WorkOrderService::class)->create(['customerName' => 'أبو سامي']);

        $this->actingAs($owner, 'tenant')->get('/scheduling?date=2026-09-01')->assertOk()->assertSee('أبو أحمد');
        $this->actingAs($owner, 'tenant')->get('/scheduling/resources')->assertOk()->assertSee('أحمد');
        $this->actingAs($owner, 'tenant')->get('/work-orders')->assertOk()->assertSee('أبو سامي');
        $this->actingAs($owner, 'tenant')->get("/work-orders/{$order->id}")->assertOk();
    }

    public function test_the_till_reports_a_clash_instead_of_failing(): void
    {
        $owner = $this->owner();
        $resource = $this->resource();
        $item = $this->service(minutes: 60);

        $this->scheduling()->book([
            'customerName' => 'الأول', 'itemId' => $item->id,
            'resourceId' => $resource->id, 'startsAt' => '2026-09-01 09:00',
        ]);

        $this->actingAs($owner, 'tenant')
            ->post('/scheduling', [
                'customerName' => 'الثاني',
                'itemId' => $item->id,
                'resourceId' => $resource->id,
                'startsAt' => '2026-09-01 09:30',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, Appointment::count());
    }

    public function test_the_screens_are_closed_to_a_vertical_without_scheduling(): void
    {
        $this->bindCurrentLicense(BusinessType::Grocery);

        $owner = $this->owner();

        $this->assertFalse(app(SchedulingService::class)->enabled());
        $this->actingAs($owner, 'tenant')->get('/scheduling')->assertForbidden();
        $this->actingAs($owner, 'tenant')->get('/work-orders')->assertForbidden();
    }
}
