<?php

namespace Tests\Feature\Tenant;

use App\Enums\BusinessType;
use App\Models\Tenant\DispenseRecord;
use App\Models\Tenant\FuelPump;
use App\Models\Tenant\FuelShift;
use App\Models\Tenant\FuelTank;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\FuelService;
use App\Services\Tenant\PharmacyService;
use App\Services\Tenant\StockService;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TenantTestCase;

class PharmacyFuelTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function owner(): TeamMember
    {
        $roles = $this->seedStandardRoles();

        return TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
    }

    private function location(): Location
    {
        return Location::default() ?? Location::create([
            'name' => 'Shop', 'kind' => 'store', 'is_default' => true, 'active' => true,
        ]);
    }

    // -- Pharmacy ----------------------------------------------------------

    private function medicine(array $attributes = []): MenuItem
    {
        return MenuItem::factory()->create(array_merge([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'name' => 'دواء',
            'kind' => 'goods',
            'track_stock' => true,
            'unit_code' => 'H87',
            'price' => 20,
        ], $attributes));
    }

    public function test_an_over_the_counter_medicine_needs_nothing_extra(): void
    {
        $this->bindCurrentLicense(BusinessType::Pharmacy);

        $item = $this->medicine(['is_prescription' => false]);

        app(PharmacyService::class)->assertDispensable($item, []);

        $this->assertTrue(true);
    }

    public function test_a_prescription_medicine_is_refused_without_a_prescriber(): void
    {
        $this->bindCurrentLicense(BusinessType::Pharmacy);

        $item = $this->medicine(['is_prescription' => true]);

        $this->expectException(RuntimeException::class);

        app(PharmacyService::class)->assertDispensable($item, ['prescriptionNumber' => 'RX-1']);
    }

    public function test_a_prescription_medicine_is_refused_without_a_number(): void
    {
        $this->bindCurrentLicense(BusinessType::Pharmacy);

        $item = $this->medicine(['is_prescription' => true]);

        $this->expectException(RuntimeException::class);

        app(PharmacyService::class)->assertDispensable($item, ['prescriberName' => 'د. سامي']);
    }

    public function test_a_controlled_substance_also_needs_the_patient_identified(): void
    {
        $this->bindCurrentLicense(BusinessType::Pharmacy);

        $item = $this->medicine(['is_prescription' => true, 'control_schedule' => 'جدول 2']);

        // The register has to name who received it, not just what the paper said.
        $this->expectException(RuntimeException::class);

        app(PharmacyService::class)->assertDispensable($item, [
            'prescriberName' => 'د. سامي',
            'prescriptionNumber' => 'RX-1',
        ]);
    }

    public function test_the_till_refuses_the_sale_and_nothing_is_recorded(): void
    {
        $this->bindCurrentLicense(BusinessType::Pharmacy);
        $this->location();

        $owner = $this->owner();
        $item = $this->medicine(['is_prescription' => true]);
        app(StockService::class)->receive($item, 10, 5);

        $this->actingAs($owner, 'tenant')
            ->post('/transfers', [
                'customer' => 'عميل',
                'paymentMethod' => 'cash',
                'hasChange' => '0',
                'items' => [['itemId' => $item->id, 'quantity' => 1, 'name' => $item->name, 'price' => '20']],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        // A refusal, not a warning: the whole transaction rolls back.
        $this->assertSame(0, Transfer::count());
        $this->assertSame(10.0, app(StockService::class)->onHand($item->id));
    }

    public function test_a_valid_dispense_writes_the_register(): void
    {
        $this->bindCurrentLicense(BusinessType::Pharmacy);
        $this->location();

        $owner = $this->owner();
        $item = $this->medicine(['is_prescription' => true, 'control_schedule' => 'جدول 2']);
        app(StockService::class)->receive($item, 10, 5);

        $this->actingAs($owner, 'tenant')
            ->post('/transfers', [
                'customer' => 'أبو أحمد',
                'paymentMethod' => 'cash',
                'hasChange' => '0',
                'items' => [[
                    'itemId' => $item->id, 'quantity' => 2, 'name' => $item->name, 'price' => '20',
                    'dispensing' => [
                        'prescriberName' => 'د. سامي',
                        'prescriptionNumber' => 'RX-77',
                        'patientIdNumber' => '900123456',
                    ],
                ]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $record = DispenseRecord::sole();

        $this->assertSame('جدول 2', $record->control_schedule);
        $this->assertSame('RX-77', $record->prescription_number);
        $this->assertSame('900123456', $record->patient_id_number);
        $this->assertSame(Transfer::sole()->id, $record->transfer_id);
        $this->assertSame(8.0, app(StockService::class)->onHand($item->id));
    }

    public function test_the_register_keeps_the_classification_of_the_day(): void
    {
        $this->bindCurrentLicense(BusinessType::Pharmacy);

        $item = $this->medicine(['is_prescription' => true, 'control_schedule' => 'جدول 2']);

        app(PharmacyService::class)->record($item, 1, ['patientName' => 'عميل']);

        // Reclassifying the medicine must not rewrite what the register says was
        // dispensed today.
        $item->update(['control_schedule' => null, 'is_prescription' => false]);

        $record = DispenseRecord::sole();

        $this->assertSame('جدول 2', $record->control_schedule);
        $this->assertTrue($record->was_prescription);
    }

    public function test_substitutes_are_found_by_active_ingredient(): void
    {
        $this->bindCurrentLicense(BusinessType::Pharmacy);

        $brand = $this->medicine(['name' => 'بنادول', 'active_ingredient' => 'باراسيتامول']);
        $this->medicine(['name' => 'ريفانين', 'active_ingredient' => 'باراسيتامول']);
        $this->medicine(['name' => 'بروفين', 'active_ingredient' => 'إيبوبروفين']);

        $this->assertSame(['ريفانين'], app(PharmacyService::class)->substitutesFor($brand)->pluck('name')->all());
    }

    public function test_the_pharmacy_screens_are_gated(): void
    {
        $this->bindCurrentLicense(BusinessType::Grocery);

        $this->assertFalse(app(PharmacyService::class)->enabled());
        $this->actingAs($this->owner(), 'tenant')->get('/pharmacy')->assertForbidden();
    }

    public function test_the_pharmacy_screens_render(): void
    {
        $this->bindCurrentLicense(BusinessType::Pharmacy);

        $owner = $this->owner();
        $item = $this->medicine(['name' => 'بنادول', 'active_ingredient' => 'باراسيتامول']);

        $this->actingAs($owner, 'tenant')->get('/pharmacy')->assertOk();
        $this->actingAs($owner, 'tenant')->get("/pharmacy/{$item->id}")->assertOk()->assertSee('بنادول');
        $this->actingAs($owner, 'tenant')->get('/pharmacy/search?term=باراسيتامول')->assertOk()->assertSee('بنادول');
    }

    // -- Fuel --------------------------------------------------------------

    private function grade(string $name = 'بنزين 95'): MenuItem
    {
        return MenuItem::factory()->create([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'name' => $name,
            'kind' => 'goods',
            'track_stock' => true,
            'unit_code' => 'LTR',
            'price' => 6.475,
            'cost' => 5.5,
        ]);
    }

    private function station(): array
    {
        $this->location();

        $item = $this->grade();

        $tank = FuelTank::create([
            'menu_item_id' => $item->id,
            'location_id' => Location::default()->id,
            'name' => 'خزان 1',
            'capacity' => 20000,
            'active' => true,
        ]);

        $pump = $tank->pumps()->create(['name' => 'مضخة 1', 'active' => true]);

        return [$item, $tank, $pump];
    }

    public function test_sales_are_the_difference_between_two_readings(): void
    {
        $this->bindCurrentLicense(BusinessType::Fuel);
        [$item] = $this->station();

        $shift = app(FuelService::class)->openShift([]);
        $reading = $shift->readings()->sole();

        app(FuelService::class)->record($shift, [
            $reading->id => ['closing' => 1000, 'test' => 0, 'price' => 6.475],
        ], []);

        $this->assertSame(1000.0, $reading->fresh()->litresSold());
        $this->assertSame(6475.0, $reading->fresh()->value());
    }

    public function test_test_litres_are_not_sales(): void
    {
        $this->bindCurrentLicense(BusinessType::Fuel);
        $this->station();

        $shift = app(FuelService::class)->openShift([]);
        $reading = $shift->readings()->sole();

        // Pumped and returned to the tank; counting them would invent a variance.
        app(FuelService::class)->record($shift, [
            $reading->id => ['closing' => 1000, 'test' => 20, 'price' => 6],
        ], []);

        $this->assertSame(980.0, $reading->fresh()->litresSold());
    }

    public function test_a_shift_carries_the_previous_closing_readings_forward(): void
    {
        $this->bindCurrentLicense(BusinessType::Fuel);
        $this->station();

        $first = app(FuelService::class)->openShift([]);
        $reading = $first->readings()->sole();

        app(FuelService::class)->record($first, [$reading->id => ['closing' => 1500]], [
            $first->dips()->sole()->id => ['closing' => 8000],
        ]);

        app(FuelService::class)->closeShift($first->fresh());

        $second = app(FuelService::class)->openShift([]);

        // Retyping an opening reading is how a night's sales go missing.
        $this->assertSame('1500.000', $second->readings()->sole()->opening_reading);
        $this->assertSame('8000.000', $second->dips()->sole()->opening_dip);
    }

    public function test_two_shifts_cannot_be_open_at_once(): void
    {
        $this->bindCurrentLicense(BusinessType::Fuel);
        $this->station();

        app(FuelService::class)->openShift([]);

        $this->expectException(RuntimeException::class);

        app(FuelService::class)->openShift([]);
    }

    public function test_reconciliation_compares_the_meters_against_the_dip(): void
    {
        $this->bindCurrentLicense(BusinessType::Fuel);
        $this->station();

        $shift = app(FuelService::class)->openShift([]);
        $reading = $shift->readings()->sole();
        $dip = $shift->dips()->sole();

        $dip->update(['opening_dip' => 10000]);

        // Meters say 1000 out; the tank says 1050 left it.
        app(FuelService::class)->record($shift->fresh(), [
            $reading->id => ['closing' => 1000, 'price' => 6],
        ], [
            $dip->id => ['closing' => 8950, 'deliveries' => 0],
        ]);

        $row = app(FuelService::class)->reconcile($shift->fresh())->sole();

        $this->assertSame(1000.0, $row['metered']);
        $this->assertSame(1050.0, $row['dipped']);

        // Negative: more left the tank than the pumps accounted for.
        $this->assertSame(-50.0, $row['variance']);
        $this->assertTrue(app(FuelService::class)->needsInvestigation($row['percent']));
    }

    public function test_a_delivery_is_added_before_the_two_sides_are_compared(): void
    {
        $this->bindCurrentLicense(BusinessType::Fuel);
        $this->station();

        $shift = app(FuelService::class)->openShift([]);
        $reading = $shift->readings()->sole();
        $dip = $shift->dips()->sole();

        $dip->update(['opening_dip' => 5000]);

        app(FuelService::class)->record($shift->fresh(), [
            $reading->id => ['closing' => 1000, 'price' => 6],
        ], [
            $dip->id => ['closing' => 14000, 'deliveries' => 10000],
        ]);

        // 5000 + 10000 - 14000 = 1000, matching the meters exactly.
        $row = app(FuelService::class)->reconcile($shift->fresh())->sole();

        $this->assertSame(1000.0, $row['dipped']);
        $this->assertSame(0.0, $row['variance']);
    }

    public function test_closing_posts_the_metered_sales_and_the_delivery(): void
    {
        $this->bindCurrentLicense(BusinessType::Fuel);
        [$item] = $this->station();

        $shift = app(FuelService::class)->openShift([]);
        $reading = $shift->readings()->sole();
        $dip = $shift->dips()->sole();

        app(FuelService::class)->record($shift, [
            $reading->id => ['closing' => 1000, 'price' => 6],
        ], [
            $dip->id => ['closing' => 4000, 'deliveries' => 5000],
        ]);

        app(FuelService::class)->closeShift($shift->fresh());

        // 5000 received, 1000 pumped out.
        $this->assertSame(4000.0, app(StockService::class)->onHand($item->id));
        $this->assertSame('closed', $shift->fresh()->status);
    }

    public function test_a_closed_shift_cannot_be_reopened_or_edited(): void
    {
        $this->bindCurrentLicense(BusinessType::Fuel);
        $this->station();

        $shift = app(FuelService::class)->openShift([]);
        $reading = $shift->readings()->sole();

        app(FuelService::class)->record($shift, [$reading->id => ['closing' => 100]], []);
        app(FuelService::class)->closeShift($shift->fresh());

        $this->expectException(RuntimeException::class);

        app(FuelService::class)->record($shift->fresh(), [], []);
    }

    public function test_the_price_in_force_is_the_latest_one_that_has_started(): void
    {
        $this->bindCurrentLicense(BusinessType::Fuel);
        [$item] = $this->station();

        app(FuelService::class)->setPrice($item, 6.100, now()->subMonth()->toDateString());
        app(FuelService::class)->setPrice($item, 6.475, now()->toDateString());
        app(FuelService::class)->setPrice($item, 7.000, now()->addWeek()->toDateString());

        // A future announcement must not price today's litres.
        $this->assertSame(6.475, app(FuelService::class)->priceFor($item->fresh()));

        // And an old shift stays readable at what it actually sold at.
        $this->assertSame(6.100, app(FuelService::class)->priceFor($item->fresh(), now()->subWeek()->toDateString()));
    }

    public function test_the_fuel_screens_render_and_are_gated(): void
    {
        $this->bindCurrentLicense(BusinessType::Fuel);
        $this->station();

        $owner = $this->owner();
        app(FuelService::class)->openShift([]);

        $this->actingAs($owner, 'tenant')->get('/fuel')->assertOk()->assertSee('مضخة 1');
        $this->actingAs($owner, 'tenant')->get('/fuel/equipment')->assertOk()->assertSee('خزان 1');
        $this->actingAs($owner, 'tenant')->get('/fuel/shifts/'.FuelShift::sole()->id)->assertOk();

        $this->bindCurrentLicense(BusinessType::Grocery);
        $this->actingAs($owner, 'tenant')->get('/fuel')->assertForbidden();
    }

    public function test_a_pump_belongs_to_exactly_one_tank(): void
    {
        $this->bindCurrentLicense(BusinessType::Fuel);
        [, $tank] = $this->station();

        $this->assertSame($tank->id, FuelPump::sole()->fuel_tank_id);
    }
}
