<?php

namespace App\Policies\Tenant;

use App\Enums\PermissionKey;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\TeamMember;

class MenuItemPolicy
{
    public function viewAny(TeamMember $user): bool
    {
        return true;
    }

    public function view(TeamMember $user, MenuItem $menuItem): bool
    {
        return true;
    }

    public function create(TeamMember $user): bool
    {
        return $user->hasPermission(PermissionKey::MenuManage);
    }

    public function update(TeamMember $user, MenuItem $menuItem): bool
    {
        return $user->hasPermission(PermissionKey::MenuManage);
    }

    public function delete(TeamMember $user, MenuItem $menuItem): bool
    {
        return $user->hasPermission(PermissionKey::MenuManage);
    }
}
