<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Bank;
use App\Models\Tenant\Customer;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Transfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global search across orders, customers, banks and catalogue entries.
 *
 * Mirrors the web SearchController's permission rules exactly - orders and
 * customers are open to every member, banks and catalogue entries only appear
 * for members who can manage them, so search can never become a way to read
 * around a permission.
 *
 * Unlike the web version this returns NO `url` field: web routes are meaningless
 * to a mobile client, which navigates by id.
 */
class SearchController extends Controller
{
    private const PER_GROUP = 5;

    private const MIN_QUERY = 2;

    public function index(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        $empty = ['orders' => [], 'customers' => [], 'banks' => [], 'menuItems' => []];

        // A one-character query matches most of the table; not worth the scan.
        if (mb_strlen($query) < self::MIN_QUERY) {
            return response()->json($empty);
        }

        $member = $request->user();

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
                    'status' => $t->status->value,
                    'createdAt' => $t->created_at->toISOString(),
                ]),

            'customers' => Customer::where('name', 'like', "%{$query}%")
                ->orWhere('phone', 'like', "%{$query}%")
                ->take(self::PER_GROUP)
                ->get()
                ->map(fn (Customer $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'phone' => $c->phone,
                ]),

            'banks' => $member?->hasPermission(PermissionKey::BanksManage)
                ? Bank::where('name', 'like', "%{$query}%")
                    ->take(self::PER_GROUP)
                    ->get()
                    ->map(fn (Bank $b) => [
                        'id' => $b->id,
                        'name' => $b->name,
                        'type' => $b->type->value,
                        'active' => (bool) $b->active,
                    ])
                : [],

            'menuItems' => $member?->hasPermission(PermissionKey::MenuManage)
                ? MenuItem::where('name', 'like', "%{$query}%")
                    ->take(self::PER_GROUP)
                    ->get()
                    ->map(fn (MenuItem $m) => [
                        'id' => $m->id,
                        'name' => $m->name,
                        'icon' => $m->icon,
                        'price' => (float) $m->price,
                        'active' => (bool) $m->active,
                    ])
                : [],
        ]);
    }
}
