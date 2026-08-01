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
