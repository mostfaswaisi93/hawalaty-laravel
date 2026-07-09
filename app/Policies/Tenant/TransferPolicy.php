<?php

namespace App\Policies\Tenant;

use App\Enums\PermissionKey;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\Transfer;

/**
 * Reading is unrestricted for any authenticated team member of the tenant.
 * There is no separate "orders.manage" key in the 7-key permission catalog
 * (BACKEND_PROMPT.md section 5 / D:\Angular\Hawalaty\src\app\data\roles.ts),
 * so update/delete are gated on the same `transfers.create` permission as
 * create() — whoever can ring up an order can also correct or remove one.
 */
class TransferPolicy
{
    public function viewAny(TeamMember $user): bool
    {
        return true;
    }

    public function view(TeamMember $user, Transfer $transfer): bool
    {
        return true;
    }

    public function create(TeamMember $user): bool
    {
        return $user->hasPermission(PermissionKey::TransfersCreate);
    }

    public function update(TeamMember $user, Transfer $transfer): bool
    {
        return $user->hasPermission(PermissionKey::TransfersCreate);
    }

    public function delete(TeamMember $user, Transfer $transfer): bool
    {
        return $user->hasPermission(PermissionKey::TransfersCreate);
    }
}
