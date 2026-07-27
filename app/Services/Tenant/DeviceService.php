<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Models\Tenant\Device;
use App\Models\Tenant\TeamMember;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Devices are scoped to the CURRENTLY AUTHENTICATED team member, not the
 * whole tenant - Device is `team_member_id`-scoped, and the Angular
 * `/devices` page (core/devices.service.ts) only ever renders "my devices".
 */
class DeviceService
{
    public function list(TeamMember $user, ?PersonalAccessToken $currentToken): Collection
    {
        return Device::where('team_member_id', $user->id)
            ->latest('last_active_at')
            ->get()
            ->each(function (Device $device) use ($currentToken) {
                // Transient, non-persisted attribute - see DeviceResource's
                // docblock. Computed per-request, never stored as a column.
                $device->is_current = $device->isCurrent($currentToken);
            });
    }

    /**
     * Every device on the license, whoever it belongs to. Used by the devices
     * page for members holding DevicesManage, since the allowance being
     * license-wide makes "who is using the slots" the relevant question.
     */
    public function listForTenant(?PersonalAccessToken $currentToken): Collection
    {
        return Device::with('teamMember')
            ->latest('last_active_at')
            ->get()
            ->each(function (Device $device) use ($currentToken) {
                $device->is_current = $device->isCurrent($currentToken);
            });
    }

    /**
     * "Sign a device out" = revoke its actual Sanctum token AND delete the
     * Device row (not just one or the other).
     */
    public function revoke(Device $device): void
    {
        $name = $device->name;

        $device->teamMember?->tokens()->where('id', $device->sanctum_token_id)->delete();
        $device->delete();

        AuditLogger::record(AuditAction::SignedOut, AuditEntityType::Device, $name, AuditCategory::Devices);
    }
}
