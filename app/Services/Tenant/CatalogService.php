<?php

namespace App\Services\Tenant;

use App\Enums\AccountKind;
use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Models\Tenant\Account;
use App\Models\Tenant\CashBox;
use App\Models\Tenant\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CatalogService
{
    public function create(string $model, array $data): Model
    {
        $record = $model::create([...$this->columns($data), 'active' => $data['active'] ?? true]);

        $this->syncCurrencies($record, $data);
        $this->openCashAccount($record);

        AuditLogger::record(AuditAction::Created, AuditEntityType::Catalog, (string) $record->name, AuditCategory::Catalogs);

        return $record;
    }

    public function update(Model $record, array $data): Model
    {
        $record->update($this->columns($data));

        $this->syncCurrencies($record, $data);

        AuditLogger::record(AuditAction::Updated, AuditEntityType::Catalog, (string) $record->name, AuditCategory::Catalogs);

        return $record;
    }

    private function columns(array $data): array
    {
        unset($data['currencies']);

        return $data;
    }

    public function openCashAccount(Model $record): ?Account
    {
        if (! $record instanceof CashBox) {
            return null;
        }

        if (Account::where('cash_box_id', $record->getKey())->exists()) {
            return null;
        }

        $currency = $record->currencies()->first() ?? Currency::where('active', true)->first();

        if (! $currency) {
            return null;
        }

        $record->currencies()->syncWithoutDetaching([$currency->id]);

        return Account::create([
            'kind' => AccountKind::Cash->value,
            'cash_box_id' => $record->getKey(),
            'currency_id' => $currency->id,
            'holder_name' => $record->name,
            'balance' => 0,
            'active' => true,
        ]);
    }

    private function syncCurrencies(Model $record, array $data): void
    {
        if (array_key_exists('currencies', $data) && method_exists($record, 'currencies')) {
            $record->currencies()->sync($data['currencies'] ?? []);
        }
    }

    public function delete(Model $record, ?string $accountFk = null): void
    {
        if ($accountFk !== null && Account::withTrashed()->where($accountFk, $record->getKey())->exists()) {
            throw ValidationException::withMessages([
                'catalog' => __('لا يمكن حذف عنصر مستخدَم في حساب. عطّله بدلاً من ذلك.'),
            ]);
        }

        $name = (string) $record->name;
        $record->delete();

        AuditLogger::record(AuditAction::Deleted, AuditEntityType::Catalog, $name, AuditCategory::Catalogs);
    }
}
