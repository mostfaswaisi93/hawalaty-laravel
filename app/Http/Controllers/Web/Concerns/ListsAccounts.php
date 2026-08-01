<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Tenant\Account;
use Illuminate\Database\Eloquent\Collection;

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
