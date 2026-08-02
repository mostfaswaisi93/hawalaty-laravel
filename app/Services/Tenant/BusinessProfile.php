<?php

namespace App\Services\Tenant;

use App\Enums\BusinessCapability;
use App\Enums\BusinessType;
use App\Models\License;

/**
 * The current workspace's vertical, and what it switches on.
 *
 * Single point of truth for "does this tenant have module X". Controllers,
 * middleware, navigation and Blade all ask this rather than reading the license
 * directly, so the fallback for an unresolved license is decided in one place.
 */
class BusinessProfile
{
    private ?BusinessType $type = null;

    public function type(): BusinessType
    {
        if ($this->type instanceof BusinessType) {
            return $this->type;
        }

        $license = app()->bound('currentLicense') ? app('currentLicense') : null;

        return $this->type = $license instanceof License
            ? $license->businessType()
            : BusinessType::default();
    }

    public function is(BusinessType $type): bool
    {
        return $this->type() === $type;
    }

    public function has(BusinessCapability|string $capability): bool
    {
        $case = $capability instanceof BusinessCapability
            ? $capability
            : BusinessCapability::tryFrom($capability);

        return $case instanceof BusinessCapability && $this->type()->hasCapability($case);
    }

    /**
     * @param  iterable<BusinessCapability|string>  $capabilities
     */
    public function hasAny(iterable $capabilities): bool
    {
        foreach ($capabilities as $capability) {
            if ($this->has($capability)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<BusinessCapability>
     */
    public function capabilities(): array
    {
        return $this->type()->capabilities();
    }

    /**
     * Whether anything is stocked at all. Services sell time, not goods, and the
     * whole inventory surface collapses for them.
     */
    public function tracksStock(): bool
    {
        return $this->has(BusinessCapability::Inventory);
    }

    /**
     * Whether quantities may be fractional. Drives input step, rounding and the
     * decimal places shown on quantities throughout the UI.
     */
    public function allowsFractionalQuantity(): bool
    {
        return $this->has(BusinessCapability::WeighedItems)
            || $this->has(BusinessCapability::Scheduling);
    }

    public function defaultUnitCode(): string
    {
        return $this->type()->defaultUnitCode();
    }
}
