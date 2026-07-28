<?php

namespace Tests\Feature\Tenant;

use App\Enums\PermissionKey;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Role;
use App\Models\Tenant\SyncEvent;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\Transfer;
use Illuminate\Support\Facades\Notification;
use Tests\TenantTestCase;

/**
 * The drain the browser posts to after taking orders with no connection.
 *
 * The rule that matters most here is the permission one: having been offline
 * when an order was written must not become a way around a permission the member
 * does not hold. Everything a queued order goes through - validation, the
 * server-side amount, the audit trail - has to be the same as the online path.
 */
class OfflineOrderQueueTest extends TenantTestCase
{
    /** @var array{owner: Role, manager: Role, staff: Role} */
    private array $roles;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->roles = $this->seedStandardRoles();
    }

    private function signIn(?Role $role = null): TeamMember
    {
        $member = TeamMember::factory()->create(['role_id' => ($role ?? $this->roles['owner'])->id]);
        $this->actingAs($member, 'tenant');

        return $member;
    }

    private function orderPayload(): array
    {
        $item = MenuItem::factory()->create(['name' => 'شاورما', 'price' => '20.00']);

        return [
            'customer' => 'أحمد',
            'customerPhone' => '0599111222',
            'paymentMethod' => 'cash',
            'hasChange' => false,
            'items' => [['itemId' => $item->id, 'name' => $item->name, 'quantity' => 2, 'price' => 20.00]],
        ];
    }

    private function event(string $uuid, array $payload): array
    {
        return [
            'events' => [[
                'clientUuid' => $uuid,
                'entityType' => 'transfer',
                'operation' => 'create',
                'payload' => $payload,
            ]],
        ];
    }

    public function test_a_queued_order_is_created_when_it_reaches_the_server(): void
    {
        $this->signIn();

        $response = $this->postJson(route('sync.push'), $this->event('uuid-1', $this->orderPayload()))->assertOk();

        $this->assertSame([], $response->json('failed'));
        $response->assertJsonPath('applied.0', 'uuid-1');

        // Recomputed from the catalogue, never taken from the queued payload.
        $this->assertSame('40.00', Transfer::sole()->amount);
    }

    /**
     * The whole point of the client-side uuid: a browser that could not tell
     * whether its push landed will send the same batch again.
     */
    public function test_the_same_queued_order_sent_twice_creates_one_order(): void
    {
        $this->signIn();
        $batch = $this->event('uuid-dupe', $this->orderPayload());

        $this->postJson(route('sync.push'), $batch)->assertOk();
        $this->postJson(route('sync.push'), $batch)
            ->assertOk()
            ->assertJsonPath('alreadyApplied.0', 'uuid-dupe')
            ->assertJsonCount(0, 'applied');

        $this->assertSame(1, Transfer::count());
    }

    /** Being offline when it was written changes nothing about who may write it. */
    public function test_a_member_without_the_create_permission_is_refused(): void
    {
        $powerless = Role::factory()->create();
        $this->signIn($powerless);

        $this->assertFalse($powerless->fresh()->permissions()->exists());

        $this->postJson(route('sync.push'), $this->event('uuid-nope', $this->orderPayload()))
            ->assertForbidden();

        $this->assertSame(0, Transfer::count());
        $this->assertSame(0, SyncEvent::count());
    }

    /**
     * A redirect, not a 401: this is a web route behind the session guard, so
     * an expired session sends the browser to the login page. The queue treats
     * anything non-2xx as "keep the orders and try later", which is the right
     * response to a session that simply timed out during a long shift.
     */
    public function test_a_guest_cannot_drain_a_queue(): void
    {
        $this->postJson(route('sync.push'), $this->event('uuid-guest', $this->orderPayload()))
            ->assertRedirect(route('login'));

        $this->assertSame(0, Transfer::count());
    }

    /**
     * A payload that will never validate has to come back as failed rather than
     * hang in the browser's queue being retried forever.
     */
    public function test_an_unfixable_payload_comes_back_as_failed(): void
    {
        $this->signIn();

        $this->postJson(route('sync.push'), $this->event('uuid-bad', ['customer' => 'ناقص']))
            ->assertOk()
            ->assertJsonCount(0, 'applied')
            ->assertJsonPath('failed.0.clientUuid', 'uuid-bad');
    }

    public function test_the_owner_role_actually_holds_the_permission_this_gates_on(): void
    {
        $owner = TeamMember::factory()->create(['role_id' => $this->roles['owner']->id]);

        $this->assertTrue($owner->hasPermission(PermissionKey::TransfersCreate));
    }
}
