<?php

namespace App\Services\Tenant;

use App\Http\Requests\Tenant\Expense\StoreExpenseRequest;
use App\Http\Requests\Tenant\Expense\UpdateExpenseRequest;
use App\Http\Requests\Tenant\Transfer\StoreTransferRequest;
use App\Http\Requests\Tenant\Transfer\UpdateTransferRequest;
use App\Models\Tenant\Device;
use App\Models\Tenant\Expense;
use App\Models\Tenant\SyncEvent;
use App\Models\Tenant\Transfer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

/**
 * Offline sync engine - BACKEND_PROMPT.md section 10.
 *
 * Scope is `transfer` and `expense`, across all three operations. Those two are
 * what staff record with no signal; banks, catalogue entries and team members
 * are configuration, changed deliberately at a desk, and giving them an offline
 * write path would buy conflict handling nobody needs.
 *
 * Operations were create-only until now, and that was the real gap: a client
 * could record an order offline and then had no way to correct or cancel it
 * before it reached the server - the exact moment a correction is most likely.
 *
 * IDENTIFYING THE TARGET of an update or delete is the part worth understanding.
 * A record created offline has no server id yet, so the client cannot address it
 * by one. It can name the `clientUuid` of the create event instead, which this
 * resolves through the event log's own `entity_id`. That is what lets a client
 * create, edit and delete the same record offline and push all three at once.
 */
class SyncService
{
    public function __construct(
        private readonly TransferService $transferService,
        private readonly ExpenseService $expenseService,
    ) {}

    /**
     * Each event is applied inside its OWN DB::transaction() (not one
     * transaction for the whole batch), so one bad event never rolls back
     * the others.
     */
    public function push(?Device $device, array $events): array
    {
        $applied = [];
        $alreadyApplied = [];
        $failed = [];

        foreach ($events as $event) {
            try {
                DB::transaction(function () use ($event, $device, &$applied, &$alreadyApplied) {
                    // Idempotency: a client_uuid already recorded means this
                    // event was already applied on a previous push (e.g. a
                    // flaky connection caused a resend).
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

    /**
     * Hands back the raw event log for the requesting device to apply
     * locally itself; this does not interpret the events, only returns
     * them in order.
     */
    public function pull(int $since): Collection
    {
        return SyncEvent::where('server_sequence', '>', $since)
            ->orderBy('server_sequence')
            ->get();
    }

    /** @return string the affected record's server id */
    private function apply(array $event): string
    {
        $payload = $event['payload'];

        return match ($event['operation']) {
            'create' => (string) match ($event['entityType']) {
                'transfer' => $this->createTransfer($payload)->id,
                'expense' => $this->createExpense($payload)->id,
            },
            'update' => (string) match ($event['entityType']) {
                'transfer' => $this->updateTransfer($payload)->id,
                'expense' => $this->updateExpense($payload)->id,
            },
            'delete' => $this->deleteRecord($event['entityType'], $payload),
        };
    }

    /**
     * Validates the payload with StoreTransferRequest's own rules() (rather
     * than duplicating a rules array), then delegates to TransferService -
     * the actual creation logic (amount computed server-side, etc.) is NOT
     * duplicated here.
     */
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

    /**
     * An order's only editable field is its status - the amount and line items
     * are computed server-side and must never be writable from a client
     * payload - which is why this reuses UpdateTransferRequest's rules rather
     * than accepting whatever the client sent.
     */
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

    /**
     * Deleting something already gone SUCCEEDS. The client asked for an end
     * state that already holds, and sync converges on end states - failing here
     * would leave a client retrying forever over a record two devices both
     * deleted.
     */
    private function deleteRecord(string $entityType, array $payload): string
    {
        $query = $entityType === 'transfer' ? Transfer::query() : Expense::query();
        $record = $this->find($query, $entityType, $payload);

        if ($record === null) {
            // Nothing to delete, but the log row still needs an entity_id.
            // Whatever the client named the record is the honest answer.
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

    /** @throws RuntimeException when the target cannot be found */
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

    /**
     * A create event's client uuid -> the server id it produced. This is the
     * whole reason a client can edit something it created while offline.
     */
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
