<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Bank;
use App\Models\Tenant\Customer;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Transfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs the header search pill (layouts/app.blade.php). Each result group
 * mirrors the permission that gates its own index page - orders/customers
 * are open to every team member (see History/CustomerController), banks and
 * menu items only show up for members who can manage them.
 */
class SearchController extends Controller
{
    private const PER_GROUP = 5;

    public function index(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json([
                'orders' => [],
                'customers' => [],
                'banks' => [],
                'menuItems' => [],
            ]);
        }

        $member = auth('tenant')->user();

        return response()->json([
            'orders' => Transfer::where('customer', 'like', "%{$query}%")
                ->latest()
                ->take(self::PER_GROUP)
                ->get()
                ->map(fn (Transfer $t) => [
                    'id' => $t->id,
                    'customer' => $t->customer,
                    'itemName' => $t->item_name,
                    'amount' => (float) $t->amount,
                    'url' => route('history.index', ['search' => $t->customer]),
                ]),
            'customers' => Customer::where('name', 'like', "%{$query}%")
                ->orWhere('phone', 'like', "%{$query}%")
                ->take(self::PER_GROUP)
                ->get()
                ->map(fn (Customer $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'phone' => $c->phone,
                    'url' => route('customers.show', $c->id),
                ]),
            'banks' => $member?->hasPermission(PermissionKey::BanksManage)
                ? Bank::where('name', 'like', "%{$query}%")
                    ->take(self::PER_GROUP)
                    ->get()
                    ->map(fn (Bank $b) => [
                        'id' => $b->id,
                        'name' => $b->name,
                        'url' => route('banks.index', ['search' => $b->name]),
                    ])
                : [],
            'menuItems' => $member?->hasPermission(PermissionKey::MenuManage)
                ? MenuItem::where('name', 'like', "%{$query}%")
                    ->take(self::PER_GROUP)
                    ->get()
                    ->map(fn (MenuItem $m) => [
                        'id' => $m->id,
                        'name' => $m->name,
                        'price' => (float) $m->price,
                        'url' => route('menu.index'),
                    ])
                : [],
        ]);
    }
}
