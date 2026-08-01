<?php

namespace App\Policies\Tenant;

use App\Enums\PermissionKey;
use App\Models\Tenant\Device;
use App\Models\Tenant\TeamMember;

class DevicePolicy
{
    public function viewAny(TeamMember $user): bool
    {
        return true;
    }

    public function delete(TeamMember $user, Device $device): bool
    {
        return $device->team_member_id === $user->id
            || $user->hasPermission(PermissionKey::DevicesManage);
    }
}
