<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class RoleService
{
    public function list(): Collection
    {
        return Role::with('permissions')->get();
    }

    /**
     * Custom (tenant-created) role: `is_system` is always false and `key`
     * is always null - only the 3 built-in roles cloned at provisioning
     * time ever get a key/is_system=true.
     */
    public function create(array $data): Role
    {
        $role = Role::create([
            'name' => $data['name'],
            'key' => null,
            'is_system' => false,
        ]);

        $permissionIds = Permission::whereIn('key', $data['permissions'])->pluck('id');
        $role->permissions()->sync($permissionIds);
        $role->load('permissions');

        AuditLogger::record(AuditAction::Created, AuditEntityType::Role, $role->name, AuditCategory::Team);

        return $role;
    }

    /**
     * Blocks mutating `is_system` roles - RolePolicy::update() already
     * checks this too (defense in depth, per this task's instructions).
     */
    public function update(Role $role, array $data): Role
    {
        if ($role->is_system) {
            throw ValidationException::withMessages([
                'role' => 'System roles cannot be modified.',
            ]);
        }

        $role->update(['name' => $data['name']]);

        $permissionIds = Permission::whereIn('key', $data['permissions'])->pluck('id');
        $role->permissions()->sync($permissionIds);
        $role->load('permissions');

        AuditLogger::record(AuditAction::Updated, AuditEntityType::Role, $role->name, AuditCategory::Team);

        return $role;
    }

    /**
     * Blocks deleting `is_system` roles (defense in depth alongside
     * RolePolicy::delete()) and roles that still have team members
     * assigned, to avoid orphaning `team_members.role_id` (a required,
     * non-nullable foreign key).
     */
    public function delete(Role $role): void
    {
        if ($role->is_system) {
            throw ValidationException::withMessages([
                'role' => 'System roles cannot be deleted.',
            ]);
        }

        if ($role->teamMembers()->exists()) {
            throw ValidationException::withMessages([
                'role' => 'You cannot delete a role that still has team members assigned.',
            ]);
        }

        $name = $role->name;
        $role->delete();

        AuditLogger::record(AuditAction::Deleted, AuditEntityType::Role, $name, AuditCategory::Team);
    }
}
