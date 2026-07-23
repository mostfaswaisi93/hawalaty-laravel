<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Role;
use App\Models\Tenant\TeamMember;
use Tests\TenantTestCase;

/**
 * Proof-of-concept: confirms actingAs($member, 'tenant') plus the
 * manually-configured `tenant` connection from TenantTestCase is enough to
 * exercise tenant web routes end-to-end, without needing
 * session('license_code') — ResolveTenantConnectionFromSession only
 * re-resolves the connection when that session key is present (see the
 * middleware's docblock), so it simply passes through here and leaves
 * whatever TenantTestCase already configured in place.
 */
class HttpLayerSmokeTest extends TenantTestCase
{
    public function test_a_member_without_the_permission_is_forbidden(): void
    {
        // None of the 3 standard roles lack transfers.create (even Staff has
        // only that one) — a bare custom role with zero permissions attached
        // is the only way to exercise the "no permission" path.
        $powerlessRole = Role::factory()->create();
        $member = TeamMember::factory()->create(['role_id' => $powerlessRole->id]);
        $item = MenuItem::factory()->create();

        $this->actingAs($member, 'tenant')
            ->post('/transfers', [
                'customer' => 'Test',
                'paymentMethod' => 'cash',
                'hasChange' => '0',
                'items' => [['itemId' => $item->id, 'quantity' => 1, 'name' => $item->name, 'price' => (string) $item->price]],
            ])
            ->assertForbidden();
    }

    public function test_a_member_with_the_permission_can_create_a_transfer(): void
    {
        $roles = $this->seedStandardRoles();
        $owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
        $item = MenuItem::factory()->create();

        $this->actingAs($owner, 'tenant')
            ->post('/transfers', [
                'customer' => 'Test',
                'paymentMethod' => 'cash',
                'hasChange' => '0',
                'items' => [['itemId' => $item->id, 'quantity' => 1, 'name' => $item->name, 'price' => (string) $item->price]],
            ])
            ->assertRedirect(route('transfers.index'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/transfers')->assertRedirect('/login');
    }
}
