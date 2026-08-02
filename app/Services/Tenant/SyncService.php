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

class SyncService
{
    public function __construct(
        private readonly TransferService $transferService,
        private readonly ExpenseService $expenseService,
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
