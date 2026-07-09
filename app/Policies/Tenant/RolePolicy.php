<?php

namespace App\Policies\Tenant;

use App\Enums\PermissionKey;
use App\Models\Tenant\Role;
use App\Models\Tenant\TeamMember;

/**
 * System roles (owner/manager/staff) are read-only at the authorization
 * layer regardless of the actor's permissions — RoleService also re-checks
 * this (defense in depth, see BACKEND_PROMPT.md task instructions).
 */
class RolePolicy
{
    public function viewAny(TeamMember $user): bool
    {
        return true;
    }

    public function create(TeamMember $user): bool
    {
        return $user->hasPermission(PermissionKey::TeamManage);
    }

    public function update(TeamMember $user, Role $role): bool
    {
        if ($role->is_system) {
            return false;
        }

        return $user->hasPermission(PermissionKey::TeamManage);
    }

    public function delete(TeamMember $user, Role $role): bool
    {
        if ($role->is_system) {
            return false;
        }

        return $user->hasPermission(PermissionKey::TeamManage);
    }
}
