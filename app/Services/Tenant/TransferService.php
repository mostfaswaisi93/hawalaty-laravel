<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Enums\StockMovementType;
use App\Enums\TransferStatus;
use App\Models\Tenant\Account;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\Transfer;
use App\Notifications\Tenant\NewOrderNotification;
use App\Notifications\Tenant\OrderStatusChangedNotification;
use App\Services\WhatsApp\WhatsAppSender;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

class TransferService
{
    public function __construct(
        private readonly CustomerService $customers,
        private readonly WhatsAppSender $whatsapp,
        private readonly AccountService $accounts,
        private readonly StockService $stock,
        private readonly ModifierService $modifiers,
        private readonly SerialService $serials,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        $query = Transfer::query()->with(['lineItems', 'account', 'changeAccount']);

        $query->whereSearch('customer', $filters['search'] ?? null);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['paymentMethod'])) {
            $query->where('payment_method', $filters['paymentMethod']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', "{$filters['from']} 00:00:00");
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', "{$filters['to']} 23:59:59");
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    public function find(string $id): Transfer
    {
        return Transfer::with(['lineItems', 'account', 'changeAccount'])->findOrFail($id);
    }

    public function create(array $data): Transfer
    {
        $transfer = DB::transaction(function () use ($data) {
            $menuItems = MenuItem::with('modifierGroups')
                ->whereIn('id', array_column($data['items'], 'itemId'))
                ->get()
                ->keyBy('id');

            /*
             * Options are resolved and priced here, once, from the database.
             * The till posts which ones were picked and nothing else about them:
             * a price that arrives from the browser is a price the customer can
             * edit.
             */
            $chosen = [];
            $amount = 0;
            $summaryParts = [];

            foreach ($data['items'] as $index => $item) {
                $menuItem = $menuItems->get($item['itemId']);
                $modifiers = $this->modifiers->resolveFor($menuItem, $item['modifiers'] ?? []);

                $chosen[$index] = $modifiers;

                $unitPrice = (float) $menuItem->price + $this->modifiers->priceDelta($modifiers);
                $amount += $item['quantity'] * $unitPrice;

                $suffix = $modifiers->isNotEmpty() ? ' ('.$modifiers->pluck('name')->implode('، ').')' : '';
                $summaryParts[] = "{$menuItem->name}{$suffix} ×{$item['quantity']}";
            }

            $discount = min(max((float) ($data['discount'] ?? 0), 0), $amount);

            $customer = $this->customers->findOrCreate($data['customer'], $data['customerPhone'] ?? null);

            $transfer = Transfer::create([
                'customer' => $data['customer'],
                'customer_id' => $customer?->id,
                'status' => TransferStatus::Pending,
                'amount' => $amount - $discount,
                'discount' => $discount,
                'item_name' => implode('، ', $summaryParts),

                'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                'payment_method' => $data['paymentMethod'],
                'has_change' => $data['hasChange'] ?? null,
                'change_method' => $data['changeMethod'] ?? null,
                'change_account_id' => $data['changeAccountId'] ?? null,
                'change_amount' => $data['changeAmount'] ?? null,

                'account_id' => $data['accountId'] ?? null,
                'sender_name' => $data['senderName'] ?? null,
                'sender_phone' => $data['senderPhone'] ?? null,
                'transferred_amount' => $data['transferredAmount'] ?? null,
                'reference_number' => $data['referenceNumber'] ?? null,

                'created_by' => auth('tenant')->id(),

                'confirmation_token' => ($data['paymentMethod'] ?? null) === 'bank' ? Str::random(48) : null,
            ]);

            foreach ($data['items'] as $index => $item) {
                $menuItem = $menuItems->get($item['itemId']);
                $modifiers = $chosen[$index] ?? collect();

                $lineItem = $transfer->lineItems()->create([
                    'menu_item_id' => $item['itemId'],
                    'name' => $menuItem->name,
                    'quantity' => $item['quantity'],
                    'price' => $menuItem->price,
                ]);

                foreach ($modifiers as $modifier) {
                    // Name and price copied, not joined: the receipt has to keep
                    // saying what was sold after the menu moves on.
                    $lineItem->modifiers()->create([
                        'modifier_id' => $modifier->id,
                        'name' => $modifier->name,
                        'price_delta' => $modifier->price_delta,
                    ]);

                    if ($modifier->consumesStock() && $modifier->component instanceof MenuItem) {
                        $this->stock->issue(
                            $modifier->component,
                            (float) $modifier->component_quantity * (float) $item['quantity'],
                            StockMovementType::Consumption,
                            null,
                            [
                                'reference_type' => 'transfer',
                                'reference_id' => $transfer->id,
                                'reason' => __('إضافة :modifier', ['modifier' => $modifier->name]),
                            ],
                        );
                    }
                }

                /*
                 * Take it off the shelf.
                 *
                 * Inside the same transaction as the order: an order that exists
                 * without its stock movement overstates what is available, and
                 * the next stocktake would blame the difference on shrinkage.
                 *
                 * Services and untracked items return nothing, and lot-tracked
                 * ones fan out into one movement per lot picked - which is what
                 * makes a recall answerable later.
                 */
                $reference = ['reference_type' => 'transfer', 'reference_id' => $transfer->id];

                if ($menuItem->track_serials && $this->serials->enabled()) {
                    // The customer walked out with specific units; the record has
                    // to name them, and the warranty clock starts on each one.
                    $units = $this->serials->reserve($menuItem, $item['serials'] ?? []);

                    $this->stock->issueSerials($menuItem, $units, StockMovementType::Sale, null, $reference);
                    $this->serials->markSold($units, $menuItem, $transfer->id, $customer?->id);
                } else {
                    $this->stock->issue(
                        $menuItem,
                        (float) $item['quantity'],
                        StockMovementType::Sale,
                        null,
                        $reference,
                    );
                }
            }

            $this->postLedgerEntries($transfer);

            return $transfer;
        });

        $transfer->load([
            'lineItems.modifiers', 'changeAccount', 'customerProfile',
            'account.bankName', 'account.walletName',
        ]);

        AuditLogger::record(AuditAction::Created, AuditEntityType::Order, $transfer->id, AuditCategory::Transfers);

        Notification::send($this->teammatesToNotify(), new NewOrderNotification($transfer));

        try {
            $this->whatsapp->sendNewOrder($transfer);
        } catch (Throwable $e) {
            Log::error('WhatsApp order notification failed.', [
                'transferId' => $transfer->id,
                'accountId' => $transfer->account_id,
                'exception' => $e,
            ]);
        }

        return $transfer;
    }

    private function postLedgerEntries(Transfer $transfer): void
    {
        $label = __('طلب').' - '.$transfer->customer;

        if ($transfer->account_id && (float) $transfer->transferred_amount > 0) {
            $this->accounts->postEntry(
                Account::findOrFail($transfer->account_id),
                (float) $transfer->transferred_amount,
                0.0,
                $label,
                $transfer->id,
            );
        }

        if ($transfer->change_account_id && (float) $transfer->change_amount > 0) {
            $this->accounts->postEntry(
                Account::findOrFail($transfer->change_account_id),
                0.0,
                (float) $transfer->change_amount,
                __('باقٍ').' - '.$transfer->customer,
                $transfer->id,
            );
        }
    }

    public function updateStatus(Transfer $transfer, array $data): Transfer
    {
        $transfer->update(['status' => $data['status']]);
        $transfer->load(['lineItems', 'account', 'changeAccount']);

        AuditLogger::record(AuditAction::Updated, AuditEntityType::Order, $transfer->id, AuditCategory::Transfers);

        if (in_array($transfer->status, [TransferStatus::Completed, TransferStatus::Failed], true)) {
            Notification::send($this->teammatesToNotify(), new OrderStatusChangedNotification($transfer));
        }

        return $transfer;
    }

    private function teammatesToNotify(): Collection
    {
        $actorId = auth('tenant')->id();

        return TeamMember::where('active', true)
            ->when($actorId, fn ($query) => $query->where('id', '!=', $actorId))
            ->get();
    }

    public function delete(Transfer $transfer): void
    {
        $id = $transfer->id;
        $transfer->delete();

        AuditLogger::record(AuditAction::Deleted, AuditEntityType::Order, $id, AuditCategory::Transfers);
    }
}
