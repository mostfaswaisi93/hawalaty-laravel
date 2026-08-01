<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Models\Tenant\Device;
use App\Models\Tenant\TeamMember;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\PersonalAccessToken;

class DeviceService
{
    public function list(TeamMember $user, ?PersonalAccessToken $currentToken): Collection
    {
        return Device::where('team_member_id', $user->id)
            ->latest('last_active_at')
            ->get()
            ->each(function (Device $device) use ($currentToken) {
                $device->is_current = $device->isCurrent($currentToken);
            });
    }

    public function listForTenant(?PersonalAccessToken $currentToken): Collection
    {
        return Device::with('teamMember')
            ->latest('last_active_at')
            ->get()
            ->each(function (Device $device) use ($currentToken) {
                $device->is_current = $device->isCurrent($currentToken);
            });
    }

    public function revoke(Device $device): void
    {
        $name = $device->name;

        $device->teamMember?->tokens()->where('id', $device->sanctum_token_id)->delete();
        $device->delete();

        AuditLogger::record(AuditAction::SignedOut, AuditEntityType::Device, $name, AuditCategory::Devices);
    }
}
