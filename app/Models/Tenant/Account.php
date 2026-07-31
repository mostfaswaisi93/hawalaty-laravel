<?php

namespace App\Models\Tenant;

use App\Enums\AccountKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A person's bank or e-wallet, with its own accounting ledger. `balance` is the
 * stored running balance kept in step with ledger_entries by
 * App\Services\Tenant\AccountService::postEntry. Phase 2b.
 *
 * Which catalog FKs / fields are populated depends on `kind`: a bank account
 * fills bank_name_id + account_type_id + iban; a wallet account fills
 * wallet_name_id + wallet_type_id + wallet_phone. Both carry a currency, a
 * holder name and (optionally) contact + WhatsApp numbers.
 */
#[Fillable([
    'kind', 'bank_name_id', 'account_type_id', 'iban',
    'wallet_name_id', 'wallet_type_id', 'wallet_phone',
    'cash_box_id',
    'currency_id', 'holder_name', 'phone', 'whatsapp_phone', 'balance', 'active',
    // Provenance of an account migrated from the legacy `banks` table (Phase
    // 2c); null for accounts created afresh. Used to backfill transfers and to
    // keep the migration idempotent.
    'legacy_bank_id',
])]
class Account extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'kind' => AccountKind::class,
            'balance' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function bankName(): BelongsTo
    {
        return $this->belongsTo(BankName::class);
    }

    public function accountType(): BelongsTo
    {
        return $this->belongsTo(AccountType::class);
    }

    public function walletName(): BelongsTo
    {
        return $this->belongsTo(WalletName::class);
    }

    public function walletType(): BelongsTo
    {
        return $this->belongsTo(WalletType::class);
    }

    public function cashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /** Orders whose invoice is this account (Phase 2c repoints these). */
    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class);
    }

    public function changeTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'change_account_id');
    }

    /** Where this account is held: the bank, the wallet, or the cash box. */
    public function institutionName(): ?string
    {
        return match ($this->kind) {
            AccountKind::Bank => $this->bankName?->name,
            AccountKind::Wallet => $this->walletName?->name,
            AccountKind::Cash => $this->cashBox?->name,
            default => null,
        };
    }

    /** The account/wallet type. A cash box has no sub-type. */
    public function typeName(): ?string
    {
        return match ($this->kind) {
            AccountKind::Bank => $this->accountType?->name,
            AccountKind::Wallet => $this->walletType?->name,
            default => null,
        };
    }

    /**
     * How this account reads on screen: "<institution> - <holder>". The holder
     * alone is ambiguous the moment one person holds two accounts, and the
     * institution alone hides whose account it is.
     */
    public function label(): string
    {
        $institution = $this->institutionName();

        return filled($institution)
            ? $institution.' - '.$this->holder_name
            : (string) $this->holder_name;
    }
}
