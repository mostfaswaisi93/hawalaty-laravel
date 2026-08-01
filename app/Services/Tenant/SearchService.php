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

class SearchService
{
    public const MIN_QUERY = 2;

    private const PER_GROUP = 5;

    public function __construct(private readonly CatalogIcons $icons) {}

    public function isSearchable(string $query): bool
    {
        return mb_strlen(trim($query)) >= self::MIN_QUERY;
    }

    public function search(string $query, ?TeamMember $member): array
    {
        $query = trim($query);

        if (! $this->isSearchable($query)) {
            return [
                'orders' => collect(),
                'customers' => collect(),
                'banks' => collect(),
                'menuItems' => collect(),
                'teamMembers' => collect(),
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
            // Gated on TeamManage: a colleague's email is not something every
            // member should be able to enumerate through the search box.
            'teamMembers' => $member?->hasPermission(PermissionKey::TeamManage)
                ? $this->teamMembers($query)
                : collect(),
        ];
    }

    private function orders(string $query): Collection
    {
        return Transfer::query()

            ->where(function (Builder $q) use ($query) {
                $q->whereSearch(['customer', 'item_name', 'sender_name', 'sender_phone'], $query)

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

    private function banks(string $query): Collection
    {
        return Account::query()
            ->with(['bankName', 'walletName'])

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

    private function teamMembers(string $query): Collection
    {
        return TeamMember::query()
            ->whereSearch(['name', 'email'], $query)
            ->with('role:id,name')
            ->take(self::PER_GROUP)
            ->get()
            ->map(fn (TeamMember $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'email' => $t->email,
                'roleName' => $t->role?->name,
                'active' => (bool) $t->active,
            ]);
    }

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

                'icon' => $this->icons->resolve($m->icon, $m->menuCategory?->icon),
                'categoryName' => $m->menuCategory?->name,
                'price' => (float) $m->price,
                'active' => (bool) $m->active,
            ]);
    }
}
