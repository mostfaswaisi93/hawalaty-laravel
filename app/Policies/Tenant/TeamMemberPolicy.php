<?php

namespace App\Policies\Tenant;

use App\Enums\PermissionKey;
use App\Models\Tenant\TeamMember;

class TeamMemberPolicy
{
    public function viewAny(TeamMember $user): bool
    {
        return true;
    }

    public function create(TeamMember $user): bool
    {
        return $user->hasPermission(PermissionKey::TeamManage);
    }

    public function update(TeamMember $user, TeamMember $teamMember): bool
    {
        return $user->hasPermission(PermissionKey::TeamManage);
    }

    public function delete(TeamMember $user, TeamMember $teamMember): bool
    {
        return $user->hasPermission(PermissionKey::TeamManage);
    }
}
