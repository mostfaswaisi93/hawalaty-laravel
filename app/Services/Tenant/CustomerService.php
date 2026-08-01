<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;

class CustomerService
{
    public function list(?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = Customer::query()
            ->withCount('transfers as order_count')
            ->withSum('transfers as total_spent', 'amount')
            ->withMax('transfers as last_order_at', 'created_at');

        $query->whereSearch(['name', 'phone'], $search);

        return $query->orderByDesc('last_order_at')->paginate($perPage)->withQueryString();
    }

    public function find(string $id): Customer
    {
        return Customer::query()
            ->withCount('transfers as order_count')
            ->withSum('transfers as total_spent', 'amount')
            ->findOrFail($id);
    }

    public function transfersFor(Customer $customer, int $perPage = 20): LengthAwarePaginator
    {
        return $customer->transfers()
            ->with('lineItems')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

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

        if ($name !== '' && $name !== $customer->name) {
            $customer->update(['name' => $name]);
        }

        return $customer;
    }
}
