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

/**
 * Accounts + their ledgers (Phase 2b).
 *
 * The ledger is the source of truth; `accounts.balance` is a cached running
 * total kept exactly in step with it. Every balance change goes through
 * postEntry(), which locks the account row, reads its balance, appends one
 * ledger entry carrying the new running balance, and writes that balance back -
 * all in one transaction, so two concurrent postings can never read the same
 * starting balance and lose one of the movements.
 */
class AccountService
{
    /**
     * Fields that never mass-assign onto an Account: `kind` decides which
     * catalog side is kept, and the irrelevant side is nulled so a crafted
     * payload can't set both a bank and a wallet on one account.
     *
     * @param  array<string, mixed>  $data
     */
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
                // Positive opening balance is a debit (balance = Σdebit − Σcredit).
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

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Account $account, array $data): Account
    {
        // `kind` is immutable after creation - changing a bank into a wallet
        // would orphan its ledger's meaning. Only the editable fields are kept.
        $fields = $this->normalise($data, $account->kind, editing: true);

        if (array_key_exists('active', $data)) {
            $fields['active'] = (bool) $data['active'];
        }

        $account->update($fields);

        AuditLogger::record(AuditAction::Updated, AuditEntityType::Account, $account->holder_name, AuditCategory::Accounts);

        return $account;
    }

    /**
     * @throws ValidationException if the account has any ledger history or is
     *                             referenced by an order - deleting it would
     *                             destroy or orphan financial records. Deactivate
     *                             it instead.
     */
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

    /**
     * Append one movement to an account's ledger and move its balance. The
     * whole read-modify-write runs under a row lock so the running balance is
     * always computed from the latest committed balance.
     */
    public function postEntry(Account $account, float $debit, float $credit, ?string $description = null, ?string $transferId = null): LedgerEntry
    {
        return DB::connection('tenant')->transaction(function () use ($account, $debit, $credit, $description, $transferId) {
            /** @var Account $locked */
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
            // Keep the caller's instance in step so a following read is correct.
            $account->setAttribute('balance', $newBalance);

            return $entry;
        });
    }

    /**
     * Keep only the fields that belong to this account's kind; null the other
     * side. On create, `kind` comes from the data; on update it is fixed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
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

        // `kind` and `currency_id` are fixed at creation - the ledger is
        // denominated in that currency - so an edit never rewrites them.
        if (! $editing) {
            $shared['kind'] = $kind->value;
            $shared['currency_id'] = $data['currency_id'] ?? null;
        }

        // Every branch nulls the other kinds' columns, so a crafted payload can
        // never leave an account claiming to be two things at once.
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
            // A drawer has no number, no IBAN and no sub-type - only which box.
            AccountKind::Cash => [
                ...$shared, ...$blank,
                'cash_box_id' => $data['cash_box_id'] ?? null,
            ],
        };
    }
}
