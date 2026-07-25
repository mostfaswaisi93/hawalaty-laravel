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

    /**
     * A team member may always sign THEIR OWN device out, even without
     * devices.manage - mirrors "you can always sign yourself out" from the
     * superseded prototype's DevicePolicy.
     */
    public function delete(TeamMember $user, Device $device): bool
    {
        return $device->team_member_id === $user->id
            || $user->hasPermission(PermissionKey::DevicesManage);
    }
}
