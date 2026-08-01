<?php

namespace Tests\Feature\Tenant;

use App\Enums\PermissionKey;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Models\Tenant\TeamMember;
use App\Services\Tenant\RoleService;
use Illuminate\Validation\ValidationException;
use Tests\TenantTestCase;

class RoleServiceTest extends TenantTestCase
{
    public function test_create_always_forces_a_custom_non_system_role_and_syncs_permissions(): void
    {
        $menuPermission = Permission::factory()->create(['key' => PermissionKey::MenuManage]);
        $banksPermission = Permission::factory()->create(['key' => PermissionKey::BanksManage]);

        $role = app(RoleService::class)->create([
            'name' => 'مدير الفرع',
            'permissions' => [PermissionKey::MenuManage->value, PermissionKey::BanksManage->value],

            'is_system' => true,
            'key' => 'owner',
        ]);

        $this->assertFalse($role->is_system);
        $this->assertNull($role->key);
        $this->assertEqualsCanonicalizing(
            [$menuPermission->id, $banksPermission->id],
            $role->permissions->pluck('id')->all(),
        );
    }

    public function test_update_blocks_modifying_a_system_role_and_leaves_it_unchanged(): void
    {
        ['owner' => $owner] = $this->seedStandardRoles();
        $originalPermissionIds = $owner->permissions->pluck('id')->sort()->values()->all();
        $onlyPermission = $owner->permissions->first();

        $this->expectException(ValidationException::class);

        try {
            app(RoleService::class)->update($owner, [
                'name' => 'Hacked Owner',
                'permissions' => [$onlyPermission->key->value],
            ]);
        } finally {
            $owner->refresh();
            $this->assertSame('Owner', $owner->name);
            $this->assertEqualsCanonicalizing($originalPermissionIds, $owner->permissions->pluck('id')->sort()->values()->all());
        }
    }

    public function test_update_renames_and_re_syncs_permissions_for_a_custom_role(): void
    {
        $permissionA = Permission::factory()->create(['key' => PermissionKey::MenuManage]);
        $permissionB = Permission::factory()->create(['key' => PermissionKey::BanksManage]);
        $permissionC = Permission::factory()->create(['key' => PermissionKey::ExpensesManage]);

        $role = Role::factory()->create(['name' => 'Old Name']);
        $role->permissions()->attach([$permissionA->id, $permissionB->id]);

        $updated = app(RoleService::class)->update($role, [
            'name' => 'New Name',
            'permissions' => [PermissionKey::ExpensesManage->value],
        ]);

        $this->assertSame('New Name', $updated->name);
        $this->assertEqualsCanonicalizing([$permissionC->id], $updated->permissions->pluck('id')->all());
    }

    public function test_delete_blocks_deleting_a_system_role(): void
    {
        ['owner' => $owner] = $this->seedStandardRoles();

        $this->expectException(ValidationException::class);

        try {
            app(RoleService::class)->delete($owner);
        } finally {
            $this->assertNotNull(Role::find($owner->id));
        }
    }

    public function test_delete_blocks_deleting_a_role_that_still_has_team_members(): void
    {
        $role = Role::factory()->create();
        TeamMember::factory()->create(['role_id' => $role->id]);

        $this->expectException(ValidationException::class);

        try {
            app(RoleService::class)->delete($role);
        } finally {
            $this->assertNotNull(Role::find($role->id));
        }
    }

    public function test_delete_succeeds_for_a_custom_role_with_no_team_members(): void
    {
        $role = Role::factory()->create();

        app(RoleService::class)->delete($role);

        $this->assertNull(Role::find($role->id));
    }

    public function test_staff_role_can_only_create_transfers(): void
    {
        ['staff' => $staffRole] = $this->seedStandardRoles();
        $staff = TeamMember::factory()->create(['role_id' => $staffRole->id]);

        $this->assertTrue($staff->hasPermission(PermissionKey::TransfersCreate));
        $this->assertFalse($staff->hasPermission(PermissionKey::TeamManage));
        $this->assertFalse($staff->hasPermission(PermissionKey::MenuManage));
    }

    public function test_manager_role_has_every_permission_except_team_manage(): void
    {
        ['manager' => $managerRole] = $this->seedStandardRoles();
        $manager = TeamMember::factory()->create(['role_id' => $managerRole->id]);

        foreach (PermissionKey::cases() as $key) {
            if ($key === PermissionKey::TeamManage) {
                $this->assertFalse($manager->hasPermission($key));
            } else {
                $this->assertTrue($manager->hasPermission($key));
            }
        }
    }

    public function test_owner_role_has_every_permission(): void
    {
        ['owner' => $ownerRole] = $this->seedStandardRoles();
        $owner = TeamMember::factory()->create(['role_id' => $ownerRole->id]);

        foreach (PermissionKey::cases() as $key) {
            $this->assertTrue($owner->hasPermission($key));
        }
    }
}
