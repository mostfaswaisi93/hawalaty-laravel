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

        $query->whereSearch(['name', 'phone'], $search);

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
    /**
     * Resolves the customer for an order. IDENTITY IS THE PHONE NUMBER.
     *
     * Matching on the name was wrong both ways round: two different people called
     * "أحمد" collided into one record and pooled their history, while the same
     * person typed "أحمد محمد" once and "احمد محمد" the next time split in two.
     * The number is what actually identifies someone.
     *
     * The name is therefore just a LABEL on that number, and the most recent
     * spelling wins - correcting a typo on a new order fixes the record rather
     * than forking it.
     *
     * With no phone there is nothing to identify, so no customer is created at
     * all: the order still records the typed name in `transfers.customer`, it
     * simply is not linked to a profile. That keeps the customers list free of
     * unidentifiable half-records.
     *
     * The lookup-then-insert is not atomic, so two concurrent first orders from
     * the same number can both miss. `customers.phone` is UNIQUE precisely so the
     * loser fails loudly and is re-fetched instead of silently duplicating.
     */
    public function findOrCreate(string $name, ?string $phone = null): ?Customer
    {
        $name = trim($name);
        $phone = $phone === null ? null : trim($phone);

        if ($phone === null || $phone === '') {
            return null;
        }

        $customer = Customer::where('phone', $phone)->first();

        if (! $customer) {
            try {
                return Customer::create(['name' => $name, 'phone' => $phone]);
            } catch (QueryException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }

                $customer = Customer::where('phone', $phone)->firstOrFail();
            }
        }

        // Latest spelling wins, but never blank out a known name with an empty one.
        if ($name !== '' && $name !== $customer->name) {
            $customer->update(['name' => $name]);
        }

        return $customer;
    }
}
