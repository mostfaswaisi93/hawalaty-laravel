<?php

namespace App\Services\Tenant;

use App\Enums\PermissionKey;
use App\Models\Tenant\Bank;
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
     * Orders match on far more than the customer's name now. In a transfer app
     * the thing to hand is usually a PHONE, and searching one used to return the
     * customer while returning none of their orders - the phone lives on the
     * customer row, so the order has to be reached through the relation.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function orders(string $query): Collection
    {
        $like = $this->like($query);

        return Transfer::query()
            // Wrapped, and this matters: Transfer soft-deletes, and the global
            // scope appends `AND deleted_at IS NULL` to whatever is already
            // there. Left unwrapped, `A OR B AND deleted_at IS NULL` binds the
            // scope to the LAST branch only and deleted orders leak back out.
            ->where(function (Builder $q) use ($like) {
                $q->where('customer', 'like', $like)
                    ->orWhere('item_name', 'like', $like)
                    ->orWhere('sender_name', 'like', $like)
                    ->orWhere('sender_phone', 'like', $like)
                    // `customerProfile`, not `customer`: the `customer` column
                    // holds the name typed on the order, and an attribute of
                    // that name would shadow a relation called the same thing.
                    ->orWhereHas('customerProfile', fn (Builder $c) => $c->where('phone', 'like', $like));
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
        $like = $this->like($query);

        return Customer::query()
            ->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('phone', 'like', $like))
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

    /** @return Collection<int, array<string, mixed>> */
    private function banks(string $query): Collection
    {
        $like = $this->like($query);

        return Bank::query()
            // Wrapped for the same soft-delete reason as orders().
            ->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like))
            ->take(self::PER_GROUP)
            ->get()
            ->map(fn (Bank $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'code' => $b->code,
                'type' => $b->type->value,
                'active' => (bool) $b->active,
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function menuItems(string $query): Collection
    {
        $like = $this->like($query);

        return MenuItem::query()
            ->where('name', 'like', $like)
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

    /**
     * `%` and `_` are wildcards in LIKE, and `\` escapes them. Left raw, a query
     * of "%" matches every row - a cheap way to dump the tables through a search
     * box that is supposed to need two real characters.
     */
    private function like(string $query): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query).'%';
    }
}
