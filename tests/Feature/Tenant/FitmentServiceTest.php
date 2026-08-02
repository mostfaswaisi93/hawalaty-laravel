<?php

namespace Tests\Feature\Tenant;

use App\Enums\BusinessType;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\VehicleMake;
use App\Models\Tenant\VehicleModel;
use App\Services\Tenant\FitmentService;
use App\Services\Tenant\StockService;
use App\Support\Vin;
use RuntimeException;
use Tests\TenantTestCase;

class FitmentServiceTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bindCurrentLicense(BusinessType::AutoParts);
    }

    private function fitment(): FitmentService
    {
        return app(FitmentService::class);
    }

    private function part(string $name = 'فلتر زيت'): MenuItem
    {
        return MenuItem::factory()->create([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'name' => $name,
            'kind' => 'goods',
            'track_stock' => true,
            'unit_code' => 'H87',
        ]);
    }

    private function model(string $make = 'تويوتا', string $model = 'كورولا', ?string $wmi = null): VehicleModel
    {
        $vehicleMake = VehicleMake::firstOrCreate(['name' => $make], ['wmi' => $wmi, 'active' => true]);

        return $vehicleMake->models()->create(['name' => $model, 'active' => true]);
    }

    private function owner(): TeamMember
    {
        $roles = $this->seedStandardRoles();

        return TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
    }

    // -- Fitment -----------------------------------------------------------

    public function test_a_part_is_found_for_a_vehicle_and_year(): void
    {
        $part = $this->part();
        $corolla = $this->model();

        $this->fitment()->replaceFitments($part, [
            ['vehicleModelId' => $corolla->id, 'yearFrom' => 2014, 'yearTo' => 2019],
        ]);

        $this->assertCount(1, $this->fitment()->partsFor($corolla->id, 2016));
        $this->assertCount(0, $this->fitment()->partsFor($corolla->id, 2021));
    }

    public function test_an_open_ended_range_keeps_matching(): void
    {
        $part = $this->part();
        $corolla = $this->model();

        // "From 2015 onwards" is a real answer, not missing data.
        $this->fitment()->replaceFitments($part, [
            ['vehicleModelId' => $corolla->id, 'yearFrom' => 2015, 'yearTo' => null],
        ]);

        $this->assertCount(1, $this->fitment()->partsFor($corolla->id, 2030));
        $this->assertCount(0, $this->fitment()->partsFor($corolla->id, 2010));
    }

    public function test_no_year_given_matches_every_record(): void
    {
        $part = $this->part();
        $corolla = $this->model();

        $this->fitment()->replaceFitments($part, [
            ['vehicleModelId' => $corolla->id, 'yearFrom' => 2014, 'yearTo' => 2019],
        ]);

        $this->assertCount(1, $this->fitment()->partsFor($corolla->id, null));
    }

    public function test_a_backwards_year_range_is_straightened(): void
    {
        $part = $this->part();
        $corolla = $this->model();

        // A slip at the keyboard would otherwise make the part fit nothing.
        $this->fitment()->replaceFitments($part, [
            ['vehicleModelId' => $corolla->id, 'yearFrom' => 2019, 'yearTo' => 2014],
        ]);

        $record = $part->fresh()->fitments()->sole();

        $this->assertSame(2014, $record->year_from);
        $this->assertSame(2019, $record->year_to);
    }

    public function test_an_engine_filter_still_returns_records_that_do_not_name_one(): void
    {
        $general = $this->part('فلتر عام');
        $specific = $this->part('فلتر 1.8');
        $corolla = $this->model();

        $this->fitment()->replaceFitments($general, [['vehicleModelId' => $corolla->id]]);
        $this->fitment()->replaceFitments($specific, [['vehicleModelId' => $corolla->id, 'engine' => '1.8']]);

        // A record with no engine qualification fits every engine.
        $names = $this->fitment()->partsFor($corolla->id, null, '1.8')->pluck('item.name')->all();

        $this->assertContains('فلتر عام', $names);
        $this->assertContains('فلتر 1.8', $names);

        $this->assertSame(['فلتر عام'], $this->fitment()->partsFor($corolla->id, null, '2.0')->pluck('item.name')->all());
    }

    public function test_the_finder_reports_what_is_on_the_shelf(): void
    {
        Location::default() ?? Location::create(['name' => 'Shop', 'kind' => 'store', 'is_default' => true, 'active' => true]);

        $part = $this->part();
        $corolla = $this->model();

        $this->fitment()->replaceFitments($part, [['vehicleModelId' => $corolla->id]]);
        app(StockService::class)->receive($part, 6, 20);

        $this->assertSame(6.0, $this->fitment()->partsFor($corolla->id)->sole()['on_hand']);
    }

    // -- Cross-references --------------------------------------------------

    public function test_a_part_is_found_however_its_number_is_punctuated(): void
    {
        $part = $this->part();

        $this->fitment()->replaceReferences($part, [
            ['type' => 'oe', 'reference' => '0 986 424 815', 'brand' => 'Bosch'],
        ]);

        // The same number, typed four ways nobody agrees on.
        foreach (['0986424815', '0 986 424 815', '0-986-424-815', '0.986.424.815'] as $typed) {
            $this->assertCount(1, $this->fitment()->findByReference($typed), "Failed for {$typed}");
        }
    }

    public function test_the_search_also_covers_our_own_codes(): void
    {
        $part = $this->part();
        $part->update(['sku' => 'FLT-100']);

        $this->assertSame($part->id, $this->fitment()->findByReference('FLT-100')->sole()->id);
    }

    public function test_the_same_number_twice_is_stored_once(): void
    {
        $part = $this->part();

        $this->fitment()->replaceReferences($part, [
            ['type' => 'oe', 'reference' => '0986424815'],
            ['type' => 'aftermarket', 'reference' => '0 986 424 815'],
        ]);

        $this->assertSame(1, $part->fresh()->references()->count());
    }

    public function test_an_unknown_number_finds_nothing(): void
    {
        $this->part();

        $this->assertTrue($this->fitment()->findByReference('NOTHING-HERE')->isEmpty());
    }

    // -- Supersession ------------------------------------------------------

    public function test_the_supersession_chain_runs_to_the_end(): void
    {
        $old = $this->part('قديمة');
        $middle = $this->part('وسط');
        $current = $this->part('حالية');

        $this->fitment()->supersede($old, $middle->id);
        $this->fitment()->supersede($middle, $current->id);

        // Parts get replaced more than once; the counter needs the last one.
        $chain = $this->fitment()->supersessionChain($old->fresh());

        $this->assertSame(['وسط', 'حالية'], $chain->pluck('name')->all());
    }

    public function test_a_part_cannot_supersede_itself(): void
    {
        $part = $this->part();

        $this->expectException(RuntimeException::class);

        $this->fitment()->supersede($part, $part->id);
    }

    public function test_a_supersession_loop_is_refused(): void
    {
        $a = $this->part('أ');
        $b = $this->part('ب');

        $this->fitment()->supersede($a, $b->id);

        $this->expectException(RuntimeException::class);

        $this->fitment()->supersede($b->fresh(), $a->id);
    }

    // -- VIN ---------------------------------------------------------------

    public function test_a_vin_yields_its_manufacturer_code_and_year(): void
    {
        // Position 10 here is '3'. The code cycle repeats every thirty years, so
        // it reads as 2003 rather than 2033 - the later one is still in the
        // future and cannot be a model year yet.
        $decoded = Vin::decode('JTDBR32E430123456');

        $this->assertTrue($decoded['valid']);
        $this->assertSame('JTD', $decoded['wmi']);
        $this->assertSame(2003, $decoded['year']);
    }

    public function test_vin_punctuation_and_case_are_ignored(): void
    {
        $this->assertSame('JTDBR32E430123456', Vin::clean('jtd-br32e4 30123456'));
    }

    public function test_a_short_vin_is_not_well_formed(): void
    {
        $this->assertFalse(Vin::isWellFormed('JTDBR32E43'));
    }

    public function test_the_make_is_named_from_a_registered_wmi(): void
    {
        $this->model(wmi: 'JTD');

        $decoded = $this->fitment()->decodeVin('JTDBR32E430123456');

        $this->assertSame('تويوتا', $decoded['make']?->name);
    }

    public function test_an_unregistered_wmi_simply_names_nothing(): void
    {
        // Better an honest blank than a guessed manufacturer.
        $this->assertNull($this->fitment()->decodeVin('ZZZBR32E430123456')['make']);
    }

    // -- Vehicles ----------------------------------------------------------

    public function test_a_model_in_use_cannot_be_deleted(): void
    {
        $part = $this->part();
        $corolla = $this->model();

        $this->fitment()->replaceFitments($part, [['vehicleModelId' => $corolla->id]]);

        $this->expectException(RuntimeException::class);

        $this->fitment()->deleteModel($corolla->fresh());
    }

    public function test_an_unused_model_can_be_deleted(): void
    {
        $corolla = $this->model();

        $this->fitment()->deleteModel($corolla);

        $this->assertSame(0, VehicleModel::count());
    }

    // -- Screens -----------------------------------------------------------

    public function test_the_screens_render(): void
    {
        $owner = $this->owner();
        $part = $this->part();
        $corolla = $this->model(wmi: 'JTD');

        $this->fitment()->replaceFitments($part, [['vehicleModelId' => $corolla->id, 'yearFrom' => 2014]]);

        $this->actingAs($owner, 'tenant')->get('/fitment')->assertOk();
        $this->actingAs($owner, 'tenant')->get('/fitment/vehicles')->assertOk()->assertSee('تويوتا');
        $this->actingAs($owner, 'tenant')->get("/fitment/{$part->id}")->assertOk();

        $this->actingAs($owner, 'tenant')
            ->get('/fitment?modelId='.$corolla->id.'&year=2016')
            ->assertOk()
            ->assertSee('فلتر زيت');

        $this->actingAs($owner, 'tenant')
            ->get('/fitment?vin=JTDBR32E430123456')
            ->assertOk()
            ->assertSee('تويوتا');
    }

    public function test_the_screens_are_closed_to_a_vertical_without_fitment(): void
    {
        $this->bindCurrentLicense(BusinessType::Grocery);

        $this->assertFalse(app(FitmentService::class)->enabled());
        $this->actingAs($this->owner(), 'tenant')->get('/fitment')->assertForbidden();
    }
}
