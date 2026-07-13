<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Customer;
use Illuminate\Support\Collection;

/**
 * Backs the customer/recipient directory. transfers.customer stays a plain
 * text snapshot (unchanged, backward-compatible); customer_id is an
 * additive link resolved here. Matching is by exact trimmed name — simple
 * on purpose for v1, no phone-based dedupe or merge tooling yet.
 */
class CustomerService
{
    /**
     * @return Collection<int, Customer> each with order_count/total_spent
     *         aggregates and the most recent order's timestamp attached.
     */
    public function list(?string $search = null): Collection
    {
        $query = Customer::query()
            ->withCount('transfers as order_count')
            ->withSum('transfers as total_spent', 'amount')
            ->withMax('transfers as last_order_at', 'created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('last_order_at')->get();
    }

    public function find(string $id): Customer
    {
        return Customer::with(['transfers' => fn ($q) => $q->latest()->with('lineItems')])->findOrFail($id);
    }

    /**
     * Finds an existing customer by exact trimmed name, or creates one.
     * Backfills phone onto an existing customer only if they didn't already
     * have one (never silently overwrites a previously-recorded phone).
     */
    public function findOrCreate(string $name, ?string $phone = null): Customer
    {
        $name = trim($name);

        $customer = Customer::where('name', $name)->first();

        if (! $customer) {
            return Customer::create(['name' => $name, 'phone' => $phone]);
        }

        if ($phone && ! $customer->phone) {
            $customer->update(['phone' => $phone]);
        }

        return $customer;
    }
}
