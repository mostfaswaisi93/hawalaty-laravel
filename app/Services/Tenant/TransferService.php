<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Enums\TransferStatus;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\Transfer;
use App\Notifications\Tenant\NewOrderNotification;
use App\Notifications\Tenant\OrderStatusChangedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TransferService
{
    public function __construct(private readonly CustomerService $customers) {}

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

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
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
     */
    public function create(array $data): Transfer
    {
        $transfer = DB::transaction(function () use ($data) {
            $amount = 0;
            $summaryParts = [];

            foreach ($data['items'] as $item) {
                $amount += $item['quantity'] * $item['price'];
                $summaryParts[] = "{$item['name']} ×{$item['quantity']}";
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
                $transfer->lineItems()->create([
                    'menu_item_id' => $item['itemId'],
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ]);
            }

            return $transfer;
        });

        $transfer->load(['lineItems', 'bank', 'changeBank']);

        AuditLogger::record(AuditAction::Created, AuditEntityType::Order, $transfer->id, AuditCategory::Transfers);

        Notification::send($this->teammatesToNotify(), new NewOrderNotification($transfer));

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
     * @return \Illuminate\Support\Collection<int, TeamMember>
     */
    private function teammatesToNotify(): \Illuminate\Support\Collection
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
