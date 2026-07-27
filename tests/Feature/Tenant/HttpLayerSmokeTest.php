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
 * session('license_code') - ResolveTenantConnectionFromSession only
 * re-resolves the connection when that session key is present (see the
 * middleware's docblock), so it simply passes through here and leaves
 * whatever TenantTestCase already configured in place.
 */
class HttpLayerSmokeTest extends TenantTestCase
{
    public function test_a_member_without_the_permission_is_forbidden(): void
    {
        // None of the 3 standard roles lack transfers.create (even Staff has
        // only that one) - a bare custom role with zero permissions attached
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

    /**
     * The devices page now renders a license-wide allowance meter fed by
     * DeviceRegistrar. `currentLicense` is not bound in this harness, so this
     * also pins the "no license bound => unlimited, do not crash" path - the
     * alternative (treating it as a zero allowance) would lock everyone out.
     */
    public function test_the_devices_page_renders_with_the_allowance_meter(): void
    {
        $roles = $this->seedStandardRoles();
        $owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);

        $this->actingAs($owner, 'tenant')
            ->get('/devices')
            ->assertOk()
            ->assertViewHas('usage', fn ($usage) => $usage['used'] === 0 && $usage['unlimited'] === true)
            ->assertViewHas('manages', true);
    }

    /** A member without DevicesManage sees only their own devices. */
    public function test_a_member_without_the_permission_sees_only_their_own_devices(): void
    {
        $powerless = Role::factory()->create();
        $member = TeamMember::factory()->create(['role_id' => $powerless->id]);

        $this->actingAs($member, 'tenant')
            ->get('/devices')
            ->assertOk()
            ->assertViewHas('manages', false);
    }

    public function test_the_activity_log_paginates_with_a_whitelisted_page_size(): void
    {
        $roles = $this->seedStandardRoles();
        $owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);

        $this->actingAs($owner, 'tenant')->get('/activity-log')
            ->assertOk()
            ->assertViewHas('perPage', 20);

        $this->actingAs($owner, 'tenant')->get('/activity-log?per_page=50')
            ->assertOk()
            ->assertViewHas('perPage', 50);

        // Outside the whitelist falls back to the default rather than
        // honouring an unbounded page size.
        $this->actingAs($owner, 'tenant')->get('/activity-log?per_page=99999')
            ->assertOk()
            ->assertViewHas('perPage', 20);
    }
}
