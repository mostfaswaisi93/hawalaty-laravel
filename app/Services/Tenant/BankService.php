<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Models\Tenant\Bank;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class BankService
{
    /**
     * Unpaginated - used by callers that need every bank to populate a select
     * (the expense and transfer forms), where a page of 20 would silently drop
     * options.
     */
    public function list(?string $search = null): Collection
    {
        return $this->query($search)->get();
    }

    /** The banks page itself, which had no pagination and grew unbounded. */
    public function paginate(?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->query($search)->paginate($perPage)->withQueryString();
    }

    private function query(?string $search = null): Builder
    {
        return Bank::query()->whereSearch('name', $search);
    }

    public function create(array $data): Bank
    {
        // Eloquent's create() does not reflect column defaults applied by
        // the database (e.g. `active` boolean default(true) in the
        // migration) back onto the returned model unless explicitly set
        // here or the model is refreshed - set it explicitly so the
        // immediate API response (and BankResource's `active` field)
        // isn't null when the client omits it.
        $bank = Bank::create([...$data, 'active' => $data['active'] ?? true]);

        AuditLogger::record(AuditAction::Created, AuditEntityType::Bank, $bank->name, AuditCategory::Banks);

        return $bank;
    }

    public function update(Bank $bank, array $data): Bank
    {
        $bank->update($data);

        AuditLogger::record(AuditAction::Updated, AuditEntityType::Bank, $bank->name, AuditCategory::Banks);

        return $bank;
    }

    /**
     * @throws ValidationException if the bank has any expense or transfer
     *                             history - deleting it would silently destroy or orphan
     *                             financial records. Direct the caller to deactivate it
     *                             instead (the `active` toggle already exists for this).
     */
    public function delete(Bank $bank): void
    {
        $hasHistory = $bank->expenses()->exists()
            || $bank->transfers()->exists()
            || $bank->changeTransfers()->exists();

        if ($hasHistory) {
            throw ValidationException::withMessages([
                'bank' => __('لا يمكن حذف هذا البنك لأنه مرتبط بمصروفات أو حوالات سابقة. عطّله بدلاً من ذلك.'),
            ]);
        }

        $name = $bank->name;
        $bank->delete();

        AuditLogger::record(AuditAction::Deleted, AuditEntityType::Bank, $name, AuditCategory::Banks);
    }
}
