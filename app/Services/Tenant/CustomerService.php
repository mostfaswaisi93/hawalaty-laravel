<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;

/**
 * Backs the customer/recipient directory. transfers.customer stays a plain
 * text snapshot (unchanged, backward-compatible); customer_id is an
 * additive link resolved here. Matching is by exact trimmed name - simple
 * on purpose for v1, no phone-based dedupe or merge tooling yet.
 */
class CustomerService
{
    /**
     * Paginated (matching History/ActivityLog) - this directory grows
     * monotonically with tenant usage, since every order creates or reuses
     * a customer, so an unbounded get() would eventually load every
     * customer the tenant has ever had on one request.
     *
     * @return LengthAwarePaginator<int, Customer> each with order_count/total_spent
     *                                             aggregates and the most recent order's timestamp attached.
     */
    public function list(?string $search = null, int $perPage = 20): LengthAwarePaginator
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

        return $query->orderByDesc('last_order_at')->paginate($perPage)->withQueryString();
    }

    /**
     * The customer plus their headline figures as SQL aggregates.
     *
     * Deliberately does NOT eager-load `transfers`: it used to, and the detail
     * page then summed that in-memory collection for "total spent" and counted
     * it for "order count". A regular who has placed 500 orders therefore
     * loaded 500 transfers AND every one of their line items on every page
     * view, just to render two numbers and a table. The list is paginated
     * separately by transfersFor().
     */
    public function find(string $id): Customer
    {
        return Customer::query()
            ->withCount('transfers as order_count')
            ->withSum('transfers as total_spent', 'amount')
            ->findOrFail($id);
    }

    /** One page of a customer's orders, newest first. */
    public function transfersFor(Customer $customer, int $perPage = 20): LengthAwarePaginator
    {
        return $customer->transfers()
            ->with('lineItems')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Finds an existing customer by exact trimmed name, or creates one.
     * Backfills phone onto an existing customer only if they didn't already
     * have one (never silently overwrites a previously-recorded phone).
     *
     * The initial lookup + create is not atomic, so two concurrent orders
     * for the same new customer name can both miss the lookup and race to
     * create - customers.name has a unique constraint specifically to turn
     * the losing side of that race into a catchable exception rather than a
     * silent duplicate customer; on that exception we just re-fetch the row
     * the winner created and fall through to the same backfill logic below.
     */
    public function findOrCreate(string $name, ?string $phone = null): Customer
    {
        $name = trim($name);

        $customer = Customer::where('name', $name)->first();

        if (! $customer) {
            try {
                $customer = Customer::create(['name' => $name, 'phone' => $phone]);
            } catch (QueryException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }

                $customer = Customer::where('name', $name)->firstOrFail();
            }
        }

        if ($phone && ! $customer->phone) {
            $customer->update(['phone' => $phone]);
        }

        return $customer;
    }
}
