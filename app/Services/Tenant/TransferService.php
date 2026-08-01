<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
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
            $menuItems = MenuItem::whereIn('id', array_column($data['items'], 'itemId'))->get()->keyBy('id');

            $amount = 0;
            $summaryParts = [];

            foreach ($data['items'] as $item) {
                $menuItem = $menuItems->get($item['itemId']);
                $amount += $item['quantity'] * $menuItem->price;
                $summaryParts[] = "{$menuItem->name} ×{$item['quantity']}";
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

            foreach ($data['items'] as $item) {
                $menuItem = $menuItems->get($item['itemId']);
                $transfer->lineItems()->create([
                    'menu_item_id' => $item['itemId'],
                    'name' => $menuItem->name,
                    'quantity' => $item['quantity'],
                    'price' => $menuItem->price,
                ]);
            }

            $this->postLedgerEntries($transfer);

            return $transfer;
        });

        $transfer->load([
            'lineItems', 'changeAccount', 'customerProfile',
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
