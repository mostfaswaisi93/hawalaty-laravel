<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant\Role;
use App\Models\Tenant\TeamMember;
use App\Services\Tenant\TeamService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TenantTestCase;

class TeamServiceTest extends TenantTestCase
{
    public function test_create_hashes_the_password_and_loads_the_role(): void
    {
        $role = Role::factory()->create();

        $member = app(TeamService::class)->create([
            'name' => 'أحمد علي',
            'email' => 'ahmad@example.com',
            'password' => 'secret123',
            'roleId' => $role->id,
        ]);

        $this->assertNotSame('secret123', $member->password);
        $this->assertTrue(Hash::check('secret123', $member->password));
        $this->assertTrue($member->relationLoaded('role'));
        $this->assertSame($role->id, $member->role->id);
    }

    public function test_update_only_touches_fields_present_in_data(): void
    {
        $member = TeamMember::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);

        app(TeamService::class)->update($member, ['name' => 'New Name']);

        $member->refresh();
        $this->assertSame('New Name', $member->name);
        $this->assertSame('old@example.com', $member->email);
    }

    public function test_update_with_empty_string_password_keeps_the_current_password(): void
    {
        $member = TeamMember::factory()->create();
        $originalHash = $member->password;

        app(TeamService::class)->update($member, ['name' => $member->name, 'password' => '']);

        $member->refresh();
        $this->assertSame($originalHash, $member->password);
        $this->assertTrue(Hash::check('password', $member->password));
    }

    public function test_update_without_a_password_key_keeps_the_current_password(): void
    {
        $member = TeamMember::factory()->create();
        $originalHash = $member->password;

        app(TeamService::class)->update($member, ['name' => 'Renamed']);

        $member->refresh();
        $this->assertSame($originalHash, $member->password);
    }

    public function test_delete_blocks_removing_your_own_account(): void
    {
        $member = TeamMember::factory()->create();

        $this->expectException(ValidationException::class);

        try {
            app(TeamService::class)->delete($member, $member);
        } finally {
            $this->assertNotNull(TeamMember::find($member->id));
        }
    }

    public function test_delete_blocks_removing_the_last_active_owner(): void
    {
        ['owner' => $ownerRole, 'manager' => $managerRole] = $this->seedStandardRoles();

        $owner = TeamMember::factory()->create(['role_id' => $ownerRole->id, 'active' => true]);
        $manager = TeamMember::factory()->create(['role_id' => $managerRole->id]);

        $this->expectException(ValidationException::class);

        try {
            app(TeamService::class)->delete($manager, $owner);
        } finally {
            $this->assertNotNull(TeamMember::find($owner->id));
        }
    }

    public function test_delete_allows_removing_an_owner_when_a_second_active_owner_remains(): void
    {
        ['owner' => $ownerRole] = $this->seedStandardRoles();

        $ownerA = TeamMember::factory()->create(['role_id' => $ownerRole->id, 'active' => true]);
        $ownerB = TeamMember::factory()->create(['role_id' => $ownerRole->id, 'active' => true]);

        app(TeamService::class)->delete($ownerA, $ownerB);

        $this->assertNull(TeamMember::find($ownerB->id));
        $this->assertNotNull(TeamMember::find($ownerA->id));
    }

    public function test_an_inactive_backup_owner_does_not_count_as_a_remaining_owner(): void
    {
        ['owner' => $ownerRole, 'manager' => $managerRole] = $this->seedStandardRoles();

        $activeOwner = TeamMember::factory()->create(['role_id' => $ownerRole->id, 'active' => true]);
        TeamMember::factory()->inactive()->create(['role_id' => $ownerRole->id]);
        $manager = TeamMember::factory()->create(['role_id' => $managerRole->id]);

        $this->expectException(ValidationException::class);

        try {
            app(TeamService::class)->delete($manager, $activeOwner);
        } finally {
            $this->assertNotNull(TeamMember::find($activeOwner->id));
        }
    }

    public function test_deleting_an_already_inactive_owner_is_never_blocked_by_the_owner_count(): void
    {
        ['owner' => $ownerRole, 'manager' => $managerRole] = $this->seedStandardRoles();

        TeamMember::factory()->create(['role_id' => $ownerRole->id, 'active' => true]);
        $inactiveOwner = TeamMember::factory()->inactive()->create(['role_id' => $ownerRole->id]);
        $manager = TeamMember::factory()->create(['role_id' => $managerRole->id]);

        app(TeamService::class)->delete($manager, $inactiveOwner);

        $this->assertNull(TeamMember::find($inactiveOwner->id));
    }

    public function test_deleting_a_non_owner_is_never_blocked_by_the_owner_count_logic(): void
    {
        ['manager' => $managerRole, 'staff' => $staffRole] = $this->seedStandardRoles();

        $manager = TeamMember::factory()->create(['role_id' => $managerRole->id]);
        $staff = TeamMember::factory()->create(['role_id' => $staffRole->id]);

        app(TeamService::class)->delete($manager, $staff);

        $this->assertNull(TeamMember::find($staff->id));
    }

    public function test_list_filters_by_name_or_email_substring_and_eager_loads_role(): void
    {
        $role = Role::factory()->create();

        $matchByName = TeamMember::factory()->create(['name' => 'Ahmad Ali', 'email' => 'zzz@example.com', 'role_id' => $role->id]);
        $matchByEmail = TeamMember::factory()->create(['name' => 'Sara Khalid', 'email' => 'ahmad.k@example.com', 'role_id' => $role->id]);
        TeamMember::factory()->create(['name' => 'Yousef Kareem', 'email' => 'other@example.com', 'role_id' => $role->id]);

        $results = app(TeamService::class)->list('ahmad');

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing(
            [$matchByName->id, $matchByEmail->id],
            $results->pluck('id')->all(),
        );
        $this->assertTrue($results->first()->relationLoaded('role'));
    }

    public function test_list_without_a_search_term_returns_every_member(): void
    {
        $role = Role::factory()->create();
        TeamMember::factory()->count(3)->create(['role_id' => $role->id]);

        $this->assertCount(3, app(TeamService::class)->list());
    }
}
