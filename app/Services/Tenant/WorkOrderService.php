<?php

namespace App\Services\Tenant;

use App\Enums\WorkOrderStatus;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\WorkOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Jobs on the floor.
 *
 * A work order collects what a repair actually consumed - parts and hours - and
 * then hands the whole thing to the ordinary sale path to be billed. That last
 * step is the important one: invoicing here would mean a second way for stock to
 * leave and money to arrive, and two ways to do the same thing is how the two
 * stop agreeing.
 */
class WorkOrderService
{
    public function __construct(
        private readonly TransferService $transfers,
        private readonly CustomerService $customers,
    ) {}

    public function list(?string $status, ?string $search, int $perPage = 20): LengthAwarePaginator
    {
        return WorkOrder::query()
            ->with(['resource', 'lines', 'transfer'])
            ->when($status === 'open', fn ($query) => $query->open())
            ->when($status && $status !== 'open', fn ($query) => $query->where('status', $status))
            ->whereSearch(['reference', 'customer_name', 'registration', 'subject'], $search)
            ->latest('received_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): WorkOrder
    {
        return DB::connection('tenant')->transaction(function () use ($data) {
            $customer = $this->customers->findOrCreate($data['customerName'], $data['customerPhone'] ?? null);

            return WorkOrder::create([
                'reference' => $data['reference'] ?? null,
                'customer_id' => $customer?->id,
                'customer_name' => $data['customerName'],
                'customer_phone' => $data['customerPhone'] ?? null,
                'appointment_id' => $data['appointmentId'] ?? null,
                'service_resource_id' => $data['resourceId'] ?? null,
                'subject' => $data['subject'] ?? null,
                'registration' => $data['registration'] ?? null,
                'odometer' => $data['odometer'] ?? null,
                'complaint' => $data['complaint'] ?? null,
                'status' => WorkOrderStatus::Received,
                'received_at' => now(),
                'workmanship_warranty_days' => $data['workmanshipWarrantyDays'] ?? 0,
                'team_member_id' => auth('tenant')->id(),
            ]);
        });
    }

    public function update(WorkOrder $order, array $data): WorkOrder
    {
        $this->assertEditable($order);

        return DB::connection('tenant')->transaction(function () use ($order, $data) {
            $order->update([
                'service_resource_id' => $data['resourceId'] ?? $order->service_resource_id,
                'subject' => $data['subject'] ?? $order->subject,
                'registration' => $data['registration'] ?? $order->registration,
                'odometer' => $data['odometer'] ?? $order->odometer,
                'complaint' => $data['complaint'] ?? $order->complaint,
                'diagnosis' => $data['diagnosis'] ?? $order->diagnosis,
                'workmanship_warranty_days' => $data['workmanshipWarrantyDays'] ?? $order->workmanship_warranty_days,
                'status' => WorkOrderStatus::tryFrom((string) ($data['status'] ?? '')) ?? $order->status,
            ]);

            if (array_key_exists('lines', $data)) {
                $this->replaceLines($order, $data['lines'] ?? []);
            }

            return $order->fresh('lines');
        });
    }

    /**
     * Bill the job through the till.
     *
     * Everything follows from that one call: stock leaves by the same path as
     * any other sale, the ledger is posted, serials and lots are handled by the
     * rules already written for them, and the customer ends up with an ordinary
     * receipt rather than a document only this module knows how to produce.
     */
    public function invoice(WorkOrder $order, array $payment): WorkOrder
    {
        if ($order->isInvoiced()) {
            throw new RuntimeException(__('أمر الشغل مفوتَر مسبقًا.'));
        }

        $order->load('lines.item');

        if ($order->lines->isEmpty()) {
            throw new RuntimeException(__('لا يمكن فوترة أمر شغل بلا بنود.'));
        }

        return DB::connection('tenant')->transaction(function () use ($order, $payment) {
            $transfer = $this->transfers->create(array_merge([
                'customer' => $order->customer_name,
                'customerPhone' => $order->customer_phone,
                'paymentMethod' => 'cash',
                'hasChange' => false,
                'notes' => $order->diagnosis,
            ], $payment, [
                'items' => $order->lines->map(fn ($line) => [
                    'itemId' => $line->menu_item_id,
                    'name' => $line->name,
                    'quantity' => (float) $line->quantity,
                    'price' => (float) $line->unit_price,

                    // Named units and options are picked on the job card, not
                    // re-picked at the till.
                    'serials' => $payment['serials'][$line->menu_item_id] ?? [],
                ])->values()->all(),
            ]));

            $order->update([
                'transfer_id' => $transfer->id,
                'status' => WorkOrderStatus::Delivered,
                'delivered_at' => now(),
            ]);

            return $order->fresh(['lines', 'transfer']);
        });
    }

    public function cancel(WorkOrder $order): WorkOrder
    {
        if ($order->isInvoiced()) {
            throw new RuntimeException(__('لا يمكن إلغاء أمر شغل مفوتَر — أنشئ مرتجعًا بدلًا من ذلك.'));
        }

        $order->update(['status' => WorkOrderStatus::Cancelled]);

        return $order;
    }

    /**
     * @return array{open: int, awaitingApproval: int, ready: int}
     */
    public function summary(): array
    {
        $orders = WorkOrder::query()->get(['status']);

        return [
            'open' => $orders->filter(fn (WorkOrder $order) => $order->status->isOpen())->count(),
            'awaitingApproval' => $orders->where('status', WorkOrderStatus::Quoted)->count(),
            'ready' => $orders->where('status', WorkOrderStatus::Done)->count(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function replaceLines(WorkOrder $order, array $lines): void
    {
        $order->lines()->delete();

        $parts = 0.0;
        $labour = 0.0;
        $position = 0;

        foreach ($lines as $line) {
            $item = MenuItem::find($line['itemId'] ?? null);
            $quantity = (float) ($line['quantity'] ?? 0);

            if (! $item instanceof MenuItem || $quantity <= 0) {
                continue;
            }

            // Priced from the catalogue unless the counter deliberately
            // overrode it - a quoted job often is.
            $unitPrice = filled($line['unitPrice'] ?? null) ? (float) $line['unitPrice'] : (float) $item->price;
            $total = round($unitPrice * $quantity, 2);

            $order->lines()->create([
                'menu_item_id' => $item->id,
                'name' => $item->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $total,
                'position' => $position++,
            ]);

            // Split so the job card shows what the customer is paying for the
            // metal against what they are paying for the hands.
            if ($item->isService()) {
                $labour += $total;
            } else {
                $parts += $total;
            }
        }

        $order->update([
            'parts_total' => round($parts, 2),
            'labour_total' => round($labour, 2),
            'total' => round($parts + $labour, 2),
        ]);
    }

    private function assertEditable(WorkOrder $order): void
    {
        if (! $order->status->isEditable()) {
            throw new RuntimeException(__('أمر الشغل بعد التسليم أو الإلغاء لا يُعدَّل.'));
        }
    }
}
