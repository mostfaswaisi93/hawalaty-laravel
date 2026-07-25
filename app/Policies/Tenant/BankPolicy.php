<?php

namespace App\Policies\Tenant;

use App\Enums\PermissionKey;
use App\Models\Tenant\Bank;
use App\Models\Tenant\TeamMember;

/**
 * No tenant_id / cross-tenant checks here (unlike the superseded prototype's
 * BankPolicy) - physical DB-per-tenant isolation already guarantees only
 * this tenant's own Bank rows are ever reachable, see BACKEND_PROMPT.md
 * section 7. Reading is unrestricted for any authenticated team member.
 */
class BankPolicy
{
    public function viewAny(TeamMember $user): bool
    {
        return true;
    }

    public function view(TeamMember $user, Bank $bank): bool
    {
        return true;
    }

    public function create(TeamMember $user): bool
    {
        return $user->hasPermission(PermissionKey::BanksManage);
    }

    public function update(TeamMember $user, Bank $bank): bool
    {
        return $user->hasPermission(PermissionKey::BanksManage);
    }

    public function delete(TeamMember $user, Bank $bank): bool
    {
        return $user->hasPermission(PermissionKey::BanksManage);
    }
}
