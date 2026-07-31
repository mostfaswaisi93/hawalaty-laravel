<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Enums\AccountKind;
use App\Models\Tenant\Account;
use App\Models\Tenant\CashBox;
use App\Models\Tenant\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * CRUD for the six reference catalogs (see CatalogRegistry). One service for
 * all of them: every catalog row is a `name` (+ an optional `symbol` for
 * currencies, + a supported-currencies pivot for banks/wallets/cash boxes) and
 * an `active` flag, so the write path is identical.
 *
 * Deletion soft-deletes (the models use SoftDeletes) - a name still referenced
 * by a historical account must keep resolving - and is refused outright while
 * any account still uses the row (deactivate it instead).
 */
class CatalogService
{
    /**
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $data
     */
    public function create(string $model, array $data): Model
    {
        // The DB defaults `active` to true, but Eloquent's create() does not
        // reflect a DB default back onto the returned instance - set it here
        // so callers reading $record->active immediately see the real value.
        $record = $model::create([...$this->columns($data), 'active' => $data['active'] ?? true]);

        $this->syncCurrencies($record, $data);
        $this->openCashAccount($record);

        AuditLogger::record(AuditAction::Created, AuditEntityType::Catalog, (string) $record->name, AuditCategory::Catalogs);

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model $record, array $data): Model
    {
        $record->update($this->columns($data));

        $this->syncCurrencies($record, $data);

        AuditLogger::record(AuditAction::Updated, AuditEntityType::Catalog, (string) $record->name, AuditCategory::Catalogs);

        return $record;
    }

    /**
     * The `currencies` array is a pivot, not a column - keep it out of the
     * mass-assignment payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function columns(array $data): array
    {
        unset($data['currencies']);

        return $data;
    }

    /**
     * A cash box IS an account - a drawer holds money and has a balance, no
     * differently from a bank account. Making the operator create the box here
     * and then open an account for it by hand is duplicate work that only ever
     * ends with boxes that cannot be selected anywhere, so the account is
     * opened with the box.
     *
     * Skipped when there is no currency to denominate it in; the box still
     * exists and gets its account the moment one is added.
     */
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

        // The box's own currency must be a supported one, or the account form's
        // rule would reject an account the app itself created.
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

    /**
     * Sync the supported-currencies pivot for catalogs that have one (banks,
     * wallets, cash boxes). Only touched when `currencies` is present in the
     * payload, so the active-toggle partial update leaves it alone.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncCurrencies(Model $record, array $data): void
    {
        if (array_key_exists('currencies', $data) && method_exists($record, 'currencies')) {
            $record->currencies()->sync($data['currencies'] ?? []);
        }
    }

    /**
     * @param  ?string  $accountFk  the accounts column that references this
     *                              catalog (from CatalogRegistry), or null if none
     *
     * @throws ValidationException if any account still references this row.
     */
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
