<?php

namespace Tests\Feature\Tenant;

use App\Enums\PermissionKey;
use App\Models\Tenant\Role;
use App\Models\Tenant\TeamMember;
use Tests\TenantTestCase;

class RolePageTest extends TenantTestCase
{
    private function owner(): TeamMember
    {
        return TeamMember::factory()->create(['role_id' => $this->seedStandardRoles()['owner']->id]);
    }

    public function test_the_page_lists_every_role_with_its_permissions(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'tenant')->get('/roles')
            ->assertOk()
            ->assertViewHas('roles')
            ->assertViewHas('permissions')

            ->assertSee(__('إنشاء الطلبات'));
    }

    public function test_a_member_without_team_manage_cannot_reach_the_page(): void
    {
        $roles = $this->seedStandardRoles();
        $staff = TeamMember::factory()->create(['role_id' => $roles['staff']->id]);

        $this->actingAs($staff, 'tenant')->get('/roles')->assertRedirect(route('dashboard'));
    }

    public function test_a_member_without_team_manage_cannot_create_a_role(): void
    {
        $roles = $this->seedStandardRoles();
        $staff = TeamMember::factory()->create(['role_id' => $roles['staff']->id]);

        $this->actingAs($staff, 'tenant')
            ->post('/roles', ['name' => 'مخترق', 'permissions' => [PermissionKey::TeamManage->value]])
            ->assertForbidden();

        $this->assertSame(0, Role::where('name', 'مخترق')->count());
    }

    public function test_a_custom_role_can_be_created_with_chosen_permissions(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'tenant')
            ->post('/roles', [
                'name' => 'أمين صندوق',
                'permissions' => [PermissionKey::TransfersCreate->value, PermissionKey::ExpensesManage->value],
            ])
            ->assertRedirect(route('roles.index'));

        $role = Role::where('name', 'أمين صندوق')->with('permissions')->firstOrFail();

        $this->assertFalse($role->is_system, 'A tenant-created role must never be a system role.');
        $this->assertNull($role->key);
        $this->assertEqualsCanonicalizing(
            [PermissionKey::TransfersCreate->value, PermissionKey::ExpensesManage->value],
            $role->permissions->pluck('key')->map(fn ($k) => $k instanceof PermissionKey ? $k->value : $k)->all()
        );
    }

    public function test_permissions_can_be_changed_on_a_custom_role(): void
    {
        $owner = $this->owner();
        $role = Role::create(['name' => 'مؤقت', 'key' => null, 'is_system' => false]);

        $this->actingAs($owner, 'tenant')
            ->put("/roles/{$role->id}", [
                'name' => 'مؤقت',
                'permissions' => [PermissionKey::MenuManage->value],
            ])
            ->assertRedirect(route('roles.index'));

        $this->assertSame(
            [PermissionKey::MenuManage->value],
            $role->fresh()->permissions->pluck('key')->map(fn ($k) => $k instanceof PermissionKey ? $k->value : $k)->all()
        );
    }

    public function test_a_system_role_cannot_be_edited(): void
    {
        $roles = $this->seedStandardRoles();
        $owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
        $system = $roles['manager'];

        $this->actingAs($owner, 'tenant')
            ->put("/roles/{$system->id}", ['name' => 'مغيَّر', 'permissions' => [PermissionKey::TeamManage->value]])
            ->assertRedirect();

        $this->assertNotSame('مغيَّر', $system->fresh()->name);
    }

    public function test_a_system_role_cannot_be_deleted(): void
    {
        $roles = $this->seedStandardRoles();
        $owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
        $system = $roles['staff'];

        $this->actingAs($owner, 'tenant')->delete("/roles/{$system->id}")->assertRedirect();

        $this->assertNotNull($system->fresh(), 'A built-in role was deleted.');
    }

    public function test_a_role_with_members_cannot_be_deleted(): void
    {
        $owner = $this->owner();
        $role = Role::create(['name' => 'مستخدم', 'key' => null, 'is_system' => false]);
        TeamMember::factory()->create(['role_id' => $role->id]);

        $this->actingAs($owner, 'tenant')->delete("/roles/{$role->id}")->assertRedirect();

        $this->assertNotNull($role->fresh(), 'A role with members was deleted, orphaning them.');
    }

    public function test_an_unused_custom_role_can_be_deleted(): void
    {
        $owner = $this->owner();
        $role = Role::create(['name' => 'غير مستخدم', 'key' => null, 'is_system' => false]);

        $this->actingAs($owner, 'tenant')->delete("/roles/{$role->id}")
            ->assertRedirect(route('roles.index'));

        $this->assertNull($role->fresh());
    }

    public function test_a_role_must_grant_at_least_one_permission(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'tenant')
            ->post('/roles', ['name' => 'فارغ', 'permissions' => []])
            ->assertSessionHasErrors('permissions');
    }

    public function test_an_unknown_permission_key_is_rejected(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'tenant')
            ->post('/roles', ['name' => 'مزيّف', 'permissions' => ['everything.always']])
            ->assertSessionHasErrors('permissions.0');

        $this->assertSame(0, Role::where('name', 'مزيّف')->count());
    }

    public function test_every_permission_has_presentation_metadata(): void
    {
        foreach (PermissionKey::cases() as $permission) {
            $this->assertNotSame('', $permission->label(), "{$permission->value} has no label.");
            $this->assertNotSame('', $permission->description(), "{$permission->value} has no description.");
            $this->assertNotSame('', $permission->icon(), "{$permission->value} has no icon.");
        }
    }
}
