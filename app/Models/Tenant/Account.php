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

#[Fillable([
    'kind', 'bank_name_id', 'account_type_id', 'iban',
    'wallet_name_id', 'wallet_type_id', 'wallet_phone',
    'cash_box_id',
    'currency_id', 'holder_name', 'phone', 'whatsapp_phone', 'balance', 'active',

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

    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class);
    }

    public function changeTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'change_account_id');
    }

    public function institutionName(): ?string
    {
        return match ($this->kind) {
            AccountKind::Bank => $this->bankName?->name,
            AccountKind::Wallet => $this->walletName?->name,
            AccountKind::Cash => $this->cashBox?->name,
            default => null,
        };
    }

    public function typeName(): ?string
    {
        return match ($this->kind) {
            AccountKind::Bank => $this->accountType?->name,
            AccountKind::Wallet => $this->walletType?->name,
            default => null,
        };
    }

    public function label(): string
    {
        $institution = $this->institutionName();

        return filled($institution)
            ? $institution.' - '.$this->holder_name
            : (string) $this->holder_name;
    }
}
