<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant\Account;
use App\Models\Tenant\Expense;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\SyncEvent;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\SyncService;
use Illuminate\Support\Facades\Notification;
use Tests\TenantTestCase;

/**
 * The offline sync engine.
 *
 * The property that matters is CONVERGENCE: a client that was offline pushes
 * whatever it accumulated, in one batch, possibly twice, and the server ends up
 * in the state the client described. Everything below is a way that can fail -
 * a resend applying twice, one bad event taking the batch down with it, or an
 * edit to something the client created offline having nothing to attach to.
 */
class SyncServiceTest extends TenantTestCase
{
    private function service(): SyncService
    {
        return app(SyncService::class);
    }

    private function orderPayload(array $overrides = []): array
    {
        $item = MenuItem::factory()->create(['name' => 'شاورما', 'price' => '20.00']);

        return $overrides + [
            'customer' => 'أحمد',
            'customerPhone' => '0599111222',
            'paymentMethod' => 'cash',
            'hasChange' => false,
            'items' => [[
                'itemId' => $item->id,
                'name' => $item->name,
                'quantity' => 2,
                'price' => 20.00,
            ]],
        ];
    }

    private function event(string $uuid, string $type, string $op, array $payload): array
    {
        return ['clientUuid' => $uuid, 'entityType' => $type, 'operation' => $op, 'payload' => $payload];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    // --- create -----------------------------------------------------------

    public function test_a_queued_order_is_applied_and_logged(): void
    {
        $result = $this->service()->push(null, [
            $this->event('uuid-1', 'transfer', 'create', $this->orderPayload()),
        ]);

        $this->assertSame(['uuid-1'], $result['applied']);
        $this->assertSame([], $result['failed']);

        // The amount is recomputed server-side from the catalogue, never taken
        // from the client: 2 x 20.00.
        $this->assertSame('40.00', Transfer::sole()->amount);
        $this->assertSame('uuid-1', SyncEvent::sole()->client_uuid);
    }

    /**
     * A push with no registered device must succeed - the column allows null,
     * so the client never sees a raw database error.
     */
    public function test_a_push_from_an_unregistered_device_still_applies(): void
    {
        $result = $this->service()->push(null, [
            $this->event('uuid-nodevice', 'transfer', 'create', $this->orderPayload()),
        ]);

        $this->assertSame(['uuid-nodevice'], $result['applied']);
        $this->assertNull(SyncEvent::sole()->device_id);
    }

    /** A flaky connection makes clients resend; the second copy must be a no-op. */
    public function test_the_same_event_pushed_twice_is_applied_once(): void
    {
        $event = $this->event('uuid-dupe', 'transfer', 'create', $this->orderPayload());

        $this->service()->push(null, [$event]);
        $second = $this->service()->push(null, [$event]);

        $this->assertSame(['uuid-dupe'], $second['alreadyApplied']);
        $this->assertSame([], $second['applied']);
        $this->assertSame(1, Transfer::count());
    }

    /** Each event gets its own transaction, so one bad apple cannot spoil the batch. */
    public function test_one_invalid_event_does_not_roll_back_the_others(): void
    {
        $result = $this->service()->push(null, [
            $this->event('good-1', 'transfer', 'create', $this->orderPayload()),
            $this->event('bad', 'transfer', 'create', ['customer' => 'ناقص']), // no items
            $this->event('good-2', 'transfer', 'create', $this->orderPayload()),
        ]);

        $this->assertSame(['good-1', 'good-2'], $result['applied']);
        $this->assertCount(1, $result['failed']);
        $this->assertSame('bad', $result['failed'][0]['clientUuid']);
        $this->assertSame(2, Transfer::count());
    }

    // --- update -----------------------------------------------------------

    public function test_an_order_status_can_be_updated_by_server_id(): void
    {
        $transfer = Transfer::factory()->create(['status' => 'pending']);

        $result = $this->service()->push(null, [
            $this->event('uuid-upd', 'transfer', 'update', ['id' => $transfer->id, 'status' => 'completed']),
        ]);

        $this->assertSame(['uuid-upd'], $result['applied'], json_encode($result['failed']));
        $this->assertSame('completed', $transfer->fresh()->status->value);
    }

    /**
     * The case the whole design exists for: a record created offline has no
     * server id, so a later edit in the SAME batch can only name the create
     * event's uuid.
     */
    public function test_a_record_created_offline_can_be_edited_in_the_same_batch(): void
    {
        $result = $this->service()->push(null, [
            $this->event('created-offline', 'transfer', 'create', $this->orderPayload()),
            $this->event('edited-offline', 'transfer', 'update', [
                'clientUuid' => 'created-offline',
                'status' => 'completed',
            ]),
        ]);

        $this->assertSame(['created-offline', 'edited-offline'], $result['applied'], json_encode($result['failed']));
        $this->assertSame('completed', Transfer::sole()->status->value);
    }

    /** An order's amount is server-owned; a client must not be able to set it. */
    public function test_an_update_cannot_rewrite_an_orders_amount(): void
    {
        $transfer = Transfer::factory()->create(['status' => 'pending', 'amount' => '40.00']);

        $this->service()->push(null, [
            $this->event('uuid-tamper', 'transfer', 'update', [
                'id' => $transfer->id,
                'status' => 'completed',
                'amount' => '1.00',
            ]),
        ]);

        $this->assertSame('40.00', $transfer->fresh()->amount);
    }

    public function test_an_expense_can_be_updated(): void
    {
        $bank = Account::factory()->create();
        $expense = Expense::create([
            'description' => 'قديم', 'amount' => '50.00', 'account_id' => $bank->id, 'date' => '2026-07-10',
        ]);

        $result = $this->service()->push(null, [
            $this->event('uuid-exp', 'expense', 'update', ['id' => $expense->id, 'amount' => 75, 'description' => 'مصحّح']),
        ]);

        $this->assertSame(['uuid-exp'], $result['applied'], json_encode($result['failed']));
        $this->assertSame('مصحّح', $expense->fresh()->description);
        $this->assertSame('75.00', $expense->fresh()->amount);
    }

    public function test_an_update_naming_nothing_that_exists_fails_clearly(): void
    {
        $result = $this->service()->push(null, [
            $this->event('uuid-ghost', 'transfer', 'update', ['id' => 'no-such-id', 'status' => 'completed']),
        ]);

        $this->assertSame([], $result['applied']);
        $this->assertStringContainsString('No transfer matches', $result['failed'][0]['error']);
    }

    // --- delete -----------------------------------------------------------

    public function test_an_order_created_offline_can_be_deleted_by_its_client_uuid(): void
    {
        $result = $this->service()->push(null, [
            $this->event('made', 'transfer', 'create', $this->orderPayload()),
            $this->event('unmade', 'transfer', 'delete', ['clientUuid' => 'made']),
        ]);

        $this->assertSame(['made', 'unmade'], $result['applied'], json_encode($result['failed']));
        $this->assertSame(0, Transfer::count());
        // Soft-deleted, not gone: financial records survive for the audit trail.
        $this->assertSame(1, Transfer::withTrashed()->count());
    }

    /**
     * Sync converges on end states. A record two devices both deleted is in the
     * state the client asked for, so failing would make it retry forever.
     */
    public function test_deleting_something_already_deleted_succeeds(): void
    {
        $transfer = Transfer::factory()->create();
        $transfer->delete();

        $result = $this->service()->push(null, [
            $this->event('uuid-del', 'transfer', 'delete', ['id' => $transfer->id]),
        ]);

        $this->assertSame(['uuid-del'], $result['applied'], json_encode($result['failed']));
    }

    // --- pull -------------------------------------------------------------

    public function test_pull_returns_only_events_after_the_cursor_in_order(): void
    {
        $this->service()->push(null, [
            $this->event('a', 'transfer', 'create', $this->orderPayload()),
            $this->event('b', 'transfer', 'create', $this->orderPayload()),
            $this->event('c', 'transfer', 'create', $this->orderPayload()),
        ]);

        $first = SyncEvent::orderBy('server_sequence')->first();

        $rest = $this->service()->pull($first->server_sequence);

        $this->assertSame(['b', 'c'], $rest->pluck('client_uuid')->all());
        $this->assertTrue($rest->first()->server_sequence < $rest->last()->server_sequence);
    }
}
