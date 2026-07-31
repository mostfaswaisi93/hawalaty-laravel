<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Tenant\Account;
use Illuminate\Database\Eloquent\Collection;

/**
 * The active accounts, for the "which account did this money move through?"
 * select on the expenses and payroll screens.
 *
 * Unpaginated on purpose: these feed a <select>, where a page of 20 would
 * silently hide the rest. The institution relations come along so a row can be
 * labelled "<bank> - <holder>" rather than a bare holder name, which is
 * ambiguous once one person holds two accounts.
 *
 * @return Collection<int, Account>
 */
trait ListsAccounts
{
    protected function activeAccounts(): Collection
    {
        return Account::query()
            ->where('active', true)
            ->with(['bankName', 'walletName', 'cashBox', 'currency'])
            ->orderBy('holder_name')
            ->get();
    }
}
