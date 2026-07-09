<?php

namespace App\Policies\Tenant;

use App\Enums\PermissionKey;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\TeamMember;

class MenuCategoryPolicy
{
    public function viewAny(TeamMember $user): bool
    {
        return true;
    }

    public function view(TeamMember $user, MenuCategory $menuCategory): bool
    {
        return true;
    }

    public function create(TeamMember $user): bool
    {
        return $user->hasPermission(PermissionKey::MenuManage);
    }

    public function delete(TeamMember $user, MenuCategory $menuCategory): bool
    {
        return $user->hasPermission(PermissionKey::MenuManage);
    }
}
