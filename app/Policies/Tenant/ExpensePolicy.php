<?php

namespace App\Policies\Tenant;

use App\Enums\PermissionKey;
use App\Models\Tenant\Expense;
use App\Models\Tenant\TeamMember;

class ExpensePolicy
{
    public function viewAny(TeamMember $user): bool
    {
        return true;
    }

    public function view(TeamMember $user, Expense $expense): bool
    {
        return true;
    }

    public function create(TeamMember $user): bool
    {
        return $user->hasPermission(PermissionKey::ExpensesManage);
    }

    public function update(TeamMember $user, Expense $expense): bool
    {
        return $user->hasPermission(PermissionKey::ExpensesManage);
    }

    public function delete(TeamMember $user, Expense $expense): bool
    {
        return $user->hasPermission(PermissionKey::ExpensesManage);
    }
}
