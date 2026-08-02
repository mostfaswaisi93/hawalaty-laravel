<?php

namespace Tests\Feature;

use App\Enums\BusinessCapability;
use App\Enums\BusinessType;
use App\Enums\UnitOfMeasure;
use Tests\TestCase;

class BusinessTypeTest extends TestCase
{
    public function test_every_vertical_is_fully_described(): void
    {
        foreach (BusinessType::cases() as $type) {
            $this->assertNotSame('', $type->label(), "{$type->value} has no label.");
            $this->assertNotSame('', $type->icon(), "{$type->value} has no icon.");
            $this->assertNotSame('', $type->description(), "{$type->value} has no description.");
            $this->assertNotSame([], $type->capabilities(), "{$type->value} switches nothing on.");
            $this->assertNotSame([], $type->standards(), "{$type->value} cites no standards.");
            $this->assertNotSame([], $type->constantLists(), "{$type->value} seeds no constant lists.");
        }
    }

    public function test_naming_covers_all_three_slots(): void
    {
        foreach (BusinessType::cases() as $type) {
            $naming = $type->naming();

            foreach (['catalog', 'category', 'item'] as $slot) {
                $this->assertArrayHasKey($slot, $naming, "{$type->value} is missing the {$slot} label.");
                $this->assertNotSame('', $naming[$slot]);
            }
        }
    }

    public function test_default_units_are_real_uncefact_codes(): void
    {
        foreach (BusinessType::cases() as $type) {
            $this->assertInstanceOf(
                UnitOfMeasure::class,
                UnitOfMeasure::tryFrom($type->defaultUnitCode()),
                "{$type->value} defaults to a unit code that is not in UN/CEFACT Rec 20.",
            );
        }
    }

    /**
     * A capability that depends on another one being on would silently do
     * nothing - batch tracking with no ledger to attach a batch to, for example.
     */
    public function test_no_vertical_enables_a_capability_whose_prerequisite_is_off(): void
    {
        foreach (BusinessType::cases() as $type) {
            $enabled = $type->capabilities();

            foreach ($enabled as $capability) {
                foreach ($capability->requires() as $prerequisite) {
                    $this->assertContains(
                        $prerequisite,
                        $enabled,
                        "{$type->value} enables {$capability->value} without {$prerequisite->value}.",
                    );
                }
            }
        }
    }

    public function test_verticals_that_stock_goods_can_also_buy_them(): void
    {
        foreach (BusinessType::cases() as $type) {
            if ($type->hasCapability(BusinessCapability::Inventory)) {
                $this->assertTrue(
                    $type->hasCapability(BusinessCapability::Purchasing),
                    "{$type->value} tracks stock with no legitimate way for it to arrive.",
                );
            }
        }
    }

    public function test_services_do_not_carry_stock(): void
    {
        $this->assertFalse(BusinessType::Services->hasCapability(BusinessCapability::Inventory));
        $this->assertTrue(BusinessType::Services->hasCapability(BusinessCapability::Scheduling));
        $this->assertSame('HUR', BusinessType::Services->defaultUnitCode());
    }

    public function test_the_high_compliance_verticals_get_their_controls(): void
    {
        $this->assertTrue(BusinessType::Pharmacy->hasCapability(BusinessCapability::Batches));
        $this->assertTrue(BusinessType::Pharmacy->hasCapability(BusinessCapability::Prescription));
        $this->assertTrue(BusinessType::Tobacco->hasCapability(BusinessCapability::AgeRestriction));
        $this->assertTrue(BusinessType::Fuel->hasCapability(BusinessCapability::FuelStation));
        $this->assertTrue(BusinessType::Clothing->hasCapability(BusinessCapability::Variants));
        $this->assertTrue(BusinessType::Electronics->hasCapability(BusinessCapability::Serials));
        $this->assertTrue(BusinessType::AutoParts->hasCapability(BusinessCapability::Fitment));
        $this->assertTrue(BusinessType::Restaurant->hasCapability(BusinessCapability::Allergens));
    }

    public function test_fractional_verticals_do_not_default_to_a_discrete_unit(): void
    {
        foreach ([BusinessType::Fuel, BusinessType::Produce, BusinessType::Services] as $type) {
            $unit = UnitOfMeasure::from($type->defaultUnitCode());

            $this->assertFalse(
                $unit->isDiscrete(),
                "{$type->value} sells fractional quantities but defaults to a whole-number unit.",
            );
            $this->assertSame(3, $unit->decimals());
        }
    }

    public function test_discrete_units_reject_fractions(): void
    {
        $this->assertSame(0, UnitOfMeasure::Piece->decimals());
        $this->assertSame('1', UnitOfMeasure::Piece->step());
        $this->assertSame('0.001', UnitOfMeasure::Kilogram->step());
    }
}
