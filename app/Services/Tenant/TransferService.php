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

    /**
     * Backs both /transfers and /history (see BACKEND_PROMPT.md section 7 -
     * History is "same as transfers with wider filters + pagination").
     */
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
        return Transfer::with(['lineItems', 'account', 'changeAccount'])->findOrFail($id);
    }

    /**
     * Creates the Transfer + its TransferLineItems in one DB::transaction().
     * `amount` is always computed server-side as the sum of quantity ×
     * price across line items - a client-sent total is never trusted.
     *
     * The per-line `name`/`price` are likewise never trusted from the
     * client: StoreTransferRequest only validates that each `itemId` exists,
     * not that the submitted price matches it, so a caller holding only
     * TransfersCreate could otherwise report an arbitrary price for a real
     * menu item and skew revenue. Every line is priced from the canonical
     * MenuItem row instead - the client's `price`/`name` are ignored.
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

            // Null when the order carries no phone: identity is the number, so
            // there is nothing to link to. `customer` below still records the
            // typed name either way, and customer_id is already nullable.
            // The discount can never exceed what was ordered: `amount` is what
            // the customer owes, and a negative one would fail the DB's own
            // non-negative CHECK.
            $discount = min(max((float) ($data['discount'] ?? 0), 0), $amount);

            $customer = $this->customers->findOrCreate($data['customer'], $data['customerPhone'] ?? null);

            $transfer = Transfer::create([
                'customer' => $data['customer'],
                'customer_id' => $customer?->id,
                'status' => TransferStatus::Pending,
                'amount' => $amount - $discount,
                'discount' => $discount,
                'item_name' => implode('، ', $summaryParts),
                'payment_method' => $data['paymentMethod'],
                'has_change' => $data['hasChange'] ?? null,
                'change_method' => $data['changeMethod'] ?? null,
                'change_account_id' => $data['changeAccountId'] ?? null,
                'change_amount' => $data['changeAmount'] ?? null,
                // The account this order posts to, and posts its ledger entry against.
                'account_id' => $data['accountId'] ?? null,
                'sender_name' => $data['senderName'] ?? null,
                'sender_phone' => $data['senderPhone'] ?? null,
                'transferred_amount' => $data['transferredAmount'] ?? null,
                'reference_number' => $data['referenceNumber'] ?? null,
                // Whoever raised the order, so the bank owner's later
                // confirmation notifies exactly this person - null on the API/
                // sync paths that carry no logged-in team member.
                'created_by' => auth('tenant')->id(),
                // Only a bank/wallet order is confirmable, so only it gets a
                // token. 48 random chars is the unguessable credential the
                // public confirmation link carries.
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

        // customerProfile carries the customer's phone, which the account owner
        // needs to reach them; it is null when the order captured no phone (see
        // CustomerService::findOrCreate). `account` carries the whatsapp_phone the
        // notification is sent to, and its institution relations let
        // NewOrderMessage name it ("<bank> - <holder>") without a lazy load.
        $transfer->load([
            'lineItems', 'changeAccount', 'customerProfile',
            'account.bankName', 'account.walletName',
        ]);

        AuditLogger::record(AuditAction::Created, AuditEntityType::Order, $transfer->id, AuditCategory::Transfers);

        Notification::send($this->teammatesToNotify(), new NewOrderNotification($transfer));

        // Notifies the bank/wallet the operator selected, when the order was
        // paid by transfer. All the "should this send at all?" rules live in
        // the sender; this call is unconditional on purpose.
        //
        // The catch is mandatory. The transfer is already committed by this
        // point, so an escaping exception would 500 the operator on an order
        // that persisted - and on the offline-sync path SyncService::push()'s
        // catch(Throwable) would mark the event failed and force the client to
        // re-push it. It would NOT roll the order back: the DB::transaction()
        // above runs on the default (platform) connection while Transfer and
        // TransferLineItem live on `tenant`.
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

    /**
     * Posts the order to the accounting ledger: an order is a movement on the
     * chosen account, not just a row in `transfers`.
     *
     * Directions follow balance = Σdebit − Σcredit:
     *  - money arriving in the account the customer transferred to is a DEBIT;
     *  - change paid back out of an account is a CREDIT.
     *
     * A cash order names no account and so moves no ledger.
     */
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

        // Only a completed/failed transition is a meaningful update to
        // announce - pending is just the default state at creation.
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
