<?php

namespace App\Services\Tenant;

use App\Http\Requests\Tenant\Expense\StoreExpenseRequest;
use App\Http\Requests\Tenant\Transfer\StoreTransferRequest;
use App\Models\Tenant\Device;
use App\Models\Tenant\Expense;
use App\Models\Tenant\SyncEvent;
use App\Models\Tenant\Transfer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Offline sync engine - implements BACKEND_PROMPT.md section 10,
 * DELIBERATELY NARROWED for this first version to `transfer` and `expense`
 * CREATE-ONLY events, matching the exact narrowing the earlier
 * (single-shared-DB) prototype made. Updates/deletes and other entity
 * types (banks, expense edits, menu items, etc.) are a deliberate future
 * extension, not an oversight - see SyncPushRequest's rules().
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
                    $existing = SyncEvent::where('client_uuid', $event['clientUuid'])->first();

                    if ($existing) {
                        $alreadyApplied[] = $event['clientUuid'];

                        return;
                    }

                    $entity = match ($event['entityType']) {
                        'transfer' => $this->applyTransfer($event['payload']),
                        'expense' => $this->applyExpense($event['payload']),
                    };

                    SyncEvent::create([
                        'device_id' => $device?->id,
                        'client_uuid' => $event['clientUuid'],
                        'entity_type' => $event['entityType'],
                        'entity_id' => (string) $entity->id,
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

    /**
     * Validates the payload with StoreTransferRequest's own rules() (rather
     * than duplicating a rules array), then delegates to TransferService -
     * the actual creation logic (amount computed server-side, etc.) is NOT
     * duplicated here, unlike the superseded prototype.
     */
    private function applyTransfer(array $payload): Transfer
    {
        $data = Validator::make($payload, (new StoreTransferRequest())->rules())->validate();

        return $this->transferService->create($data);
    }

    private function applyExpense(array $payload): Expense
    {
        $data = Validator::make($payload, (new StoreExpenseRequest())->rules())->validate();

        return $this->expenseService->create($data);
    }
}
