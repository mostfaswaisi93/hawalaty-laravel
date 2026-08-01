<?php

namespace App\Policies\Tenant;

use App\Enums\PermissionKey;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\Transfer;

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
