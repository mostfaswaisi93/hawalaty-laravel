<?php

namespace App\Policies\Tenant;

use App\Enums\PermissionKey;
use App\Models\Tenant\ConstantList;
use App\Models\Tenant\TeamMember;

class ConstantListPolicy
{
    public function viewAny(TeamMember $user): bool
    {
        return true;
    }

    public function view(TeamMember $user, ConstantList $constantList): bool
    {
        return true;
    }

    public function create(TeamMember $user): bool
    {
        return $user->hasPermission(PermissionKey::ConstantsManage);
    }

    public function update(TeamMember $user, ConstantList $constantList): bool
    {
        return $user->hasPermission(PermissionKey::ConstantsManage);
    }

    public function delete(TeamMember $user, ConstantList $constantList): bool
    {
        return $user->hasPermission(PermissionKey::ConstantsManage);
    }
}
