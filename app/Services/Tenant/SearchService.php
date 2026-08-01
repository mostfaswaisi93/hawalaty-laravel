<?php

namespace App\Services\Tenant;

use App\Enums\PermissionKey;
use App\Models\Tenant\Account;
use App\Models\Tenant\Customer;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\Transfer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The one implementation of global search, shared by the header pill
 * (Web\SearchController) and the mobile client (Api\V1\SearchController).
 *
 * It lived twice, once per controller, which is how the two quietly drifted:
 * the API already returned each catalogue entry's icon while the web copy did
 * not. Search rules are permission rules - if the two copies disagree about
 * what a member may see, one of them is a leak - so there is now only one.
 *
 * Permissions: orders and customers are open to every member (their index pages
 * are), banks and catalogue entries only appear for members who may manage them.
 * Search must never surface what a permission would otherwise hide.
 */
class SearchService
{
    /** A single character matches most of the table; not worth the scan. */
    public const MIN_QUERY = 2;

    private const PER_GROUP = 5;

    public function __construct(private readonly CatalogIcons $icons) {}

    public function isSearchable(string $query): bool
    {
        return mb_strlen(trim($query)) >= self::MIN_QUERY;
    }

    /**
     * @return array{orders: Collection<int, array<string, mixed>>, customers: Collection<int, array<string, mixed>>, banks: Collection<int, array<string, mixed>>, menuItems: Collection<int, array<string, mixed>>}
     */
    public function search(string $query, ?TeamMember $member): array
    {
        $query = trim($query);

        if (! $this->isSearchable($query)) {
            return [
                'orders' => collect(),
                'customers' => collect(),
                'banks' => collect(),
                'menuItems' => collect(),
            ];
        }

        return [
            'orders' => $this->orders($query),
            'customers' => $this->customers($query),
            'banks' => $member?->hasPermission(PermissionKey::BanksManage)
                ? $this->banks($query)
                : collect(),
            'menuItems' => $member?->hasPermission(PermissionKey::MenuManage)
                ? $this->menuItems($query)
                : collect(),
        ];
    }

    /**
     * Orders also match on PHONE, which is usually what the operator has. The
     * phone lives on the customer row, so the order is reached through the
     * relation rather than matched on the order itself.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function orders(string $query): Collection
    {
        return Transfer::query()
            // Wrapped, and this matters: Transfer soft-deletes, and the global
            // scope appends `AND deleted_at IS NULL` to whatever is already
            // there. Left unwrapped, `A OR B AND deleted_at IS NULL` binds the
            // scope to the LAST branch only and deleted orders leak back out.
            // whereSearch() parenthesises its own OR-group, and the relation
            // branch below is added inside this same wrapper.
            ->where(function (Builder $q) use ($query) {
                $q->whereSearch(['customer', 'item_name', 'sender_name', 'sender_phone'], $query)
                    // `customerProfile`, not `customer`: the `customer` column
                    // holds the name typed on the order, and an attribute of
                    // that name would shadow a relation called the same thing.
                    ->orWhereHas('customerProfile', fn (Builder $c) => $c->whereSearch('phone', $query));
            })
            ->with('customerProfile:id,phone')
            ->latest()
            ->take(self::PER_GROUP)
            ->get()
            ->map(fn (Transfer $t) => [
                'id' => $t->id,
                'customer' => $t->customer,
                'phone' => $t->customerProfile?->phone ?? $t->sender_phone,
                'itemName' => $t->item_name,
                'amount' => (float) $t->amount,
                'status' => $t->status->value,
                'createdAt' => $t->created_at?->toISOString(),
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function customers(string $query): Collection
    {
        return Customer::query()
            ->whereSearch(['name', 'phone'], $query)
            ->withCount('transfers')
            ->take(self::PER_GROUP)
            ->get()
            ->map(fn (Customer $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'orderCount' => (int) $c->transfers_count,
            ]);
    }

    /**
     * Accounts, still surfaced under the "banks" group the UI already knows.
     * Searched by the account holder AND by the institution's name, since an
     * operator looks for either ("سامي" or "بنك فلسطين").
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function banks(string $query): Collection
    {
        return Account::query()
            ->with(['bankName', 'walletName'])
            // Parenthesised by whereSearch(), for the same soft-delete reason
            // as orders().
            ->whereSearch('holder_name', $query)
            ->orWhereHas('bankName', fn ($q) => $q->whereSearch('name', $query))
            ->orWhereHas('walletName', fn ($q) => $q->whereSearch('name', $query))
            ->take(self::PER_GROUP)
            ->get()
            ->map(fn (Account $a) => [
                'id' => $a->id,
                'name' => $a->holder_name,
                'code' => $a->institutionName() ?? '',
                'type' => $a->kind->value,
                'active' => (bool) $a->active,
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function menuItems(string $query): Collection
    {
        return MenuItem::query()
            ->whereSearch('name', $query)
            ->with('menuCategory:id,name,icon')
            ->take(self::PER_GROUP)
            ->get()
            ->map(fn (MenuItem $m) => [
                'id' => $m->id,
                'name' => $m->name,
                // The entry's own icon, falling back to its category's, so the
                // result row looks like the catalogue it came from - a fuel
                // station must not get a plate of food here.
                'icon' => $this->icons->resolve($m->icon, $m->menuCategory?->icon),
                'categoryName' => $m->menuCategory?->name,
                'price' => (float) $m->price,
                'active' => (bool) $m->active,
            ]);
    }
}
