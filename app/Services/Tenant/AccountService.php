<?php

namespace App\Services\Tenant;

use App\Enums\AccountKind;
use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Models\Tenant\Account;
use App\Models\Tenant\LedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountService
{
    public function create(array $data): Account
    {
        return DB::connection('tenant')->transaction(function () use ($data) {
            $account = Account::create([
                ...$this->normalise($data),
                'balance' => 0,
                'active' => $data['active'] ?? true,
            ]);

            $opening = round((float) ($data['opening_balance'] ?? 0), 2);
            if ($opening !== 0.0) {
                $this->postEntry(
                    $account,
                    $opening > 0 ? $opening : 0.0,
                    $opening < 0 ? abs($opening) : 0.0,
                    __('رصيد افتتاحي'),
                );
            }

            AuditLogger::record(AuditAction::Created, AuditEntityType::Account, $account->holder_name, AuditCategory::Accounts);

            return $account->refresh();
        });
    }

    public function update(Account $account, array $data): Account
    {
        $fields = $this->normalise($data, $account->kind, editing: true);

        if (array_key_exists('active', $data)) {
            $fields['active'] = (bool) $data['active'];
        }

        $account->update($fields);

        AuditLogger::record(AuditAction::Updated, AuditEntityType::Account, $account->holder_name, AuditCategory::Accounts);

        return $account;
    }

    public function delete(Account $account): void
    {
        $hasHistory = $account->ledgerEntries()->exists()
            || $account->transfers()->exists()
            || $account->changeTransfers()->exists();

        if ($hasHistory) {
            throw ValidationException::withMessages([
                'account' => __('لا يمكن حذف حساب له حركات مالية. عطّله بدلاً من ذلك.'),
            ]);
        }

        $name = $account->holder_name;
        $account->delete();

        AuditLogger::record(AuditAction::Deleted, AuditEntityType::Account, $name, AuditCategory::Accounts);
    }

    public function postEntry(Account $account, float $debit, float $credit, ?string $description = null, ?string $transferId = null): LedgerEntry
    {
        return DB::connection('tenant')->transaction(function () use ($account, $debit, $credit, $description, $transferId) {
            $locked = Account::whereKey($account->getKey())->lockForUpdate()->firstOrFail();

            $newBalance = round((float) $locked->balance + $debit - $credit, 2);

            $entry = $locked->ledgerEntries()->create([
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'running_balance' => $newBalance,
                'description' => $description,
                'transfer_id' => $transferId,
            ]);

            $locked->update(['balance' => $newBalance]);

            $account->setAttribute('balance', $newBalance);

            return $entry;
        });
    }

    private function normalise(array $data, ?AccountKind $kind = null, bool $editing = false): array
    {
        $kind ??= $data['kind'] instanceof AccountKind
            ? $data['kind']
            : AccountKind::from((string) $data['kind']);

        $shared = [
            'holder_name' => $data['holder_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'whatsapp_phone' => $data['whatsapp_phone'] ?? null,
        ];

        if (! $editing) {
            $shared['kind'] = $kind->value;
            $shared['currency_id'] = $data['currency_id'] ?? null;
        }

        $blank = [
            'bank_name_id' => null, 'account_type_id' => null, 'iban' => null,
            'wallet_name_id' => null, 'wallet_type_id' => null, 'wallet_phone' => null,
            'cash_box_id' => null,
        ];

        return match ($kind) {
            AccountKind::Bank => [
                ...$shared, ...$blank,
                'bank_name_id' => $data['bank_name_id'] ?? null,
                'account_type_id' => $data['account_type_id'] ?? null,
                'iban' => $data['iban'] ?? null,
            ],
            AccountKind::Wallet => [
                ...$shared, ...$blank,
                'wallet_name_id' => $data['wallet_name_id'] ?? null,
                'wallet_type_id' => $data['wallet_type_id'] ?? null,
                'wallet_phone' => $data['wallet_phone'] ?? null,
            ],

            AccountKind::Cash => [
                ...$shared, ...$blank,
                'cash_box_id' => $data['cash_box_id'] ?? null,
            ],
        };
    }
}
