<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Enums\TransferStatus;
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
use Throwable;

class TransferService
{
    public function __construct(
        private readonly CustomerService $customers,
        private readonly WhatsAppSender $whatsapp,
    ) {}

    /**
     * Backs both /transfers and /history (see BACKEND_PROMPT.md section 7 —
     * History is "same as transfers with wider filters + pagination").
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = Transfer::query()->with(['lineItems', 'bank', 'changeBank']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('customer', 'like', "%{$search}%");
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['paymentMethod'])) {
            $query->where('payment_method', $filters['paymentMethod']);
        }

        // A plain >=/<= comparison (vs whereDate()'s DATE(created_at) wrap)
        // lets MySQL use the index on created_at instead of forcing a full
        // scan to evaluate the function on every row.
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
        return Transfer::with(['lineItems', 'bank', 'changeBank'])->findOrFail($id);
    }

    /**
     * Creates the Transfer + its TransferLineItems in one DB::transaction().
     * `amount` is always computed server-side as the sum of quantity ×
     * price across line items — a client-sent total is never trusted.
     *
     * The per-line `name`/`price` are likewise never trusted from the
     * client: StoreTransferRequest only validates that each `itemId` exists,
     * not that the submitted price matches it, so a caller holding only
     * TransfersCreate could otherwise report an arbitrary price for a real
     * menu item and skew revenue. Every line is priced from the canonical
     * MenuItem row instead — the client's `price`/`name` are ignored.
     */
    public function create(array $data): Transfer
    {
        $transfer = DB::transaction(function () use ($data) {
            $menuItems = MenuItem::whereIn('id', array_column($data['items'], 'itemId'))->get()->keyBy('id');

            $amount = 0;
            $summaryParts = [];

            foreach ($data['items'] as $item) {
                $menuItem = $menuItems->get($item['itemId']);
                $amount += $item['quantity'] * $menuItem->price;
                $summaryParts[] = "{$menuItem->name} ×{$item['quantity']}";
            }

            $customer = $this->customers->findOrCreate($data['customer'], $data['customerPhone'] ?? null);

            $transfer = Transfer::create([
                'customer' => $data['customer'],
                'customer_id' => $customer->id,
                'status' => TransferStatus::Pending,
                'amount' => $amount,
                'item_name' => implode('، ', $summaryParts),
                'payment_method' => $data['paymentMethod'],
                'has_change' => $data['hasChange'] ?? null,
                'change_method' => $data['changeMethod'] ?? null,
                'change_bank_id' => $data['changeBankId'] ?? null,
                'change_amount' => $data['changeAmount'] ?? null,
                'bank_id' => $data['bankId'] ?? null,
                'sender_name' => $data['senderName'] ?? null,
                'sender_phone' => $data['senderPhone'] ?? null,
                'transferred_amount' => $data['transferredAmount'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $menuItem = $menuItems->get($item['itemId']);
                $transfer->lineItems()->create([
                    'menu_item_id' => $item['itemId'],
                    'name' => $menuItem->name,
                    'quantity' => $item['quantity'],
                    'price' => $menuItem->price,
                ]);
            }

            return $transfer;
        });

        $transfer->load(['lineItems', 'bank', 'changeBank']);

        AuditLogger::record(AuditAction::Created, AuditEntityType::Order, $transfer->id, AuditCategory::Transfers);

        Notification::send($this->teammatesToNotify(), new NewOrderNotification($transfer));

        // Notifies the bank/wallet the operator selected, when the order was
        // paid by transfer. All the "should this send at all?" rules live in
        // the sender; this call is unconditional on purpose.
        //
        // The catch is mandatory. The transfer is already committed by this
        // point, so an escaping exception would 500 the operator on an order
        // that persisted — and on the offline-sync path SyncService::push()'s
        // catch(Throwable) would mark the event failed and force the client to
        // re-push it. It would NOT roll the order back: the DB::transaction()
        // above runs on the default (platform) connection while Transfer and
        // TransferLineItem live on `tenant`.
        try {
            $this->whatsapp->sendNewOrder($transfer);
        } catch (Throwable $e) {
            Log::error('WhatsApp order notification failed.', [
                'transferId' => $transfer->id,
                'bankId' => $transfer->bank_id,
                'exception' => $e,
            ]);
        }

        return $transfer;
    }

    public function updateStatus(Transfer $transfer, array $data): Transfer
    {
        $transfer->update(['status' => $data['status']]);
        $transfer->load(['lineItems', 'bank', 'changeBank']);

        AuditLogger::record(AuditAction::Updated, AuditEntityType::Order, $transfer->id, AuditCategory::Transfers);

        // Only a completed/failed transition is a meaningful update to
        // announce — pending is just the default state at creation.
        if (in_array($transfer->status, [TransferStatus::Completed, TransferStatus::Failed], true)) {
            Notification::send($this->teammatesToNotify(), new OrderStatusChangedNotification($transfer));
        }

        return $transfer;
    }

    /**
     * Every active team member except whoever is performing the action
     * (avoids notifying someone about their own order/status change).
     *
     * @return Collection<int, TeamMember>
     */
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
