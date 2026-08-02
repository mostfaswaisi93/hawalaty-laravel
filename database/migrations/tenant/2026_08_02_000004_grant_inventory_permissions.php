<?php

use App\Enums\PermissionKey;
use App\Enums\RoleKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Backfills the two inventory permissions into workspaces that already exist.
 *
 * Permissions are rows, not code, so a new enum case is invisible to every
 * tenant provisioned before it. Owner gets both because Owner holds everything;
 * Manager gets both because running stock is the job. Staff is left alone -
 * writing off stock is not a till-side action.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection('tenant');
        $now = now();

        $roleIds = $connection->table('roles')
            ->whereIn('key', [RoleKey::Owner->value, RoleKey::Manager->value])
            ->pluck('id');

        // No roles means no workspace yet - a database that has just been created
        // and is about to be seeded from the enum by the provisioner. Writing here
        // would only collide with that.
        if ($roleIds->isEmpty()) {
            return;
        }

        foreach ([PermissionKey::InventoryManage, PermissionKey::PurchasesManage] as $key) {
            $permissionId = $connection->table('permissions')->where('key', $key->value)->value('id');

            if (! $permissionId) {
                $permissionId = (string) Str::ulid();

                $connection->table('permissions')->insert([
                    'id' => $permissionId,
                    'key' => $key->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($roleIds as $roleId) {
                $connection->table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $connection = DB::connection('tenant');

        $permissionIds = $connection->table('permissions')
            ->whereIn('key', [PermissionKey::InventoryManage->value, PermissionKey::PurchasesManage->value])
            ->pluck('id');

        $connection->table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        $connection->table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
