<?php

namespace App\Services\Tenant;

use App\Http\Requests\Tenant\Expense\StoreExpenseRequest;
use App\Http\Requests\Tenant\Expense\UpdateExpenseRequest;
use App\Http\Requests\Tenant\SaleReturn\StoreSaleReturnRequest;
use App\Http\Requests\Tenant\Transfer\StoreTransferRequest;
use App\Http\Requests\Tenant\Transfer\UpdateTransferRequest;
use App\Http\Requests\Tenant\Warranty\StoreWarrantyClaimRequest;
use App\Models\Tenant\Device;
use App\Models\Tenant\Expense;
use App\Models\Tenant\SaleReturn;
use App\Models\Tenant\SyncEvent;
use App\Models\Tenant\Transfer;
use App\Models\Tenant\WarrantyClaim;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

class SyncService
{
    public function __construct(
        private readonly TransferService $transferService,
        private readonly ExpenseService $expenseService,
        private readonly ReturnService $returnService,
        private readonly WarrantyService $warrantyService,
    ) {}

    public function push(?Device $device, array $events): array
    {
        $applied = [];
        $alreadyApplied = [];
        $failed = [];

        foreach ($events as $event) {
            try {
                // Named connection: a bare DB::transaction() wraps the platform
                // database, so a half-applied event would have stuck.
                DB::connection('tenant')->transaction(function () use ($event, $device, &$applied, &$alreadyApplied) {
                    if (SyncEvent::where('client_uuid', $event['clientUuid'])->exists()) {
                        $alreadyApplied[] = $event['clientUuid'];

                        return;
                    }

                    $entityId = $this->apply($event);

                    SyncEvent::create([
                        'device_id' => $device?->id,
                        'client_uuid' => $event['clientUuid'],
                        'entity_type' => $event['entityType'],
                        'entity_id' => $entityId,
                        'operation' => $event['operation'],
                        'payload' => $event['payload'],
                    ]);

                    $applied[] = $event['clientUuid'];
                });
            } catch (Throwable $e) {
                $failed[] = [
                    'clientUuid' => $event['clientUuid'] ?? null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return compact('applied', 'alreadyApplied', 'failed');
    }

    public function pull(int $since): Collection
    {
        return SyncEvent::where('server_sequence', '>', $since)
            ->orderBy('server_sequence')
            ->get();
    }

    /**
     * What a disconnected device is allowed to have done.
     *
     * The line is drawn at whether an event can be replayed later without the
     * server having needed to be there at the time:
     *
     *  - A **sale** can. It is recorded as it happened, and stock is posted on
     *    arrival; the ledger is append-only and tolerates going negative, which
     *    is exactly the case of selling the last unit while a receipt is still
     *    in a drawer.
     *
     *  - A **return** can, because it arrives as a draft. Nothing moves and no
     *    money is refunded until someone completes it here, online, where the
     *    vertical's restock rules are applied.
     *
     *  - A **warranty claim** can. It is a record of a customer walking in, and
     *    the cover decision is taken from the unit's own dates, which the device
     *    already had.
     *
     *  - An **invoice** cannot, and asking for one is refused rather than
     *    quietly queued. Gapless numbering needs the sequence row locked at the
     *    moment the number is taken; a device offline cannot hold that lock, and
     *    two devices each inventing the next number produce either a duplicate
     *    or a hole - both of them audit findings. Sell offline, invoice when
     *    connected.
     */
    private function apply(array $event): string
    {
        $payload = $event['payload'];
        $entity = $event['entityType'];

        if (in_array($entity, ['invoice', 'credit_note'], true)) {
            throw new RuntimeException(__('لا تُصدَر الفواتير دون اتصال — الترقيم المتسلسل يحتاج قفلًا على الخادم.'));
        }

        return match ($event['operation']) {
            'create' => (string) match ($entity) {
                'transfer' => $this->createTransfer($payload)->id,
                'expense' => $this->createExpense($payload)->id,
                'sale_return' => $this->createReturn($payload)->id,
                'warranty_claim' => $this->createClaim($payload)->id,
            },
            'update' => (string) match ($entity) {
                'transfer' => $this->updateTransfer($payload)->id,
                'expense' => $this->updateExpense($payload)->id,
            },
            'delete' => $this->deleteRecord($entity, $payload),
        };
    }

    /**
     * A return, as a draft.
     *
     * Deliberately not completed here. Completing moves stock and refunds money,
     * and in food and medicine destroys the goods - decisions that belong to
     * someone looking at the item, not to a replayed event.
     */
    private function createReturn(array $payload): SaleReturn
    {
        $data = Validator::make($payload, StoreSaleReturnRequest::baseRules())->validate();

        return $this->returnService->create($data);
    }

    private function createClaim(array $payload): WarrantyClaim
    {
        $data = Validator::make($payload, (new StoreWarrantyClaimRequest)->rules())->validate();

        return $this->warrantyService->open($data);
    }

    private function createTransfer(array $payload): Transfer
    {
        $data = Validator::make($payload, (new StoreTransferRequest)->rules())->validate();

        return $this->transferService->create($data);
    }

    private function createExpense(array $payload): Expense
    {
        $data = Validator::make($payload, (new StoreExpenseRequest)->rules())->validate();

        return $this->expenseService->create($data);
    }

    private function updateTransfer(array $payload): Transfer
    {
        $transfer = $this->locate(Transfer::query(), 'transfer', $payload);
        $data = Validator::make($payload, (new UpdateTransferRequest)->rules())->validate();

        return $this->transferService->updateStatus($transfer, $data);
    }

    private function updateExpense(array $payload): Expense
    {
        $expense = $this->locate(Expense::query(), 'expense', $payload);
        $data = Validator::make($payload, (new UpdateExpenseRequest)->rules())->validate();

        return $this->expenseService->update($expense, $data);
    }

    private function deleteRecord(string $entityType, array $payload): string
    {
        $query = $entityType === 'transfer' ? Transfer::query() : Expense::query();
        $record = $this->find($query, $entityType, $payload);

        if ($record === null) {
            return (string) ($payload['id'] ?? $this->resolveClientUuid($payload['clientUuid'] ?? '') ?? '');
        }

        $id = (string) $record->id;

        if ($record instanceof Transfer) {
            $this->transferService->delete($record);
        } else {
            $this->expenseService->delete($record);
        }

        return $id;
    }

    private function locate(Builder $query, string $entityType, array $payload): Model
    {
        $record = $this->find($query, $entityType, $payload);

        if ($record === null) {
            throw new RuntimeException("No {$entityType} matches the given id or clientUuid.");
        }

        return $record;
    }

    private function find(Builder $query, string $entityType, array $payload): ?Model
    {
        $id = $payload['id'] ?? null;

        if (! filled($id)) {
            $id = $this->resolveClientUuid($payload['clientUuid'] ?? '');
        }

        return filled($id) ? $query->find($id) : null;
    }

    private function resolveClientUuid(string $clientUuid): ?string
    {
        if ($clientUuid === '') {
            return null;
        }

        $entityId = SyncEvent::where('client_uuid', $clientUuid)
            ->where('operation', 'create')
            ->value('entity_id');

        return filled($entityId) ? (string) $entityId : null;
    }
}
