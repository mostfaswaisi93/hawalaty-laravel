<?php

namespace Tests;

use App\Enums\BusinessType;
use App\Enums\PackageFeature;
use App\Enums\PermissionKey;
use App\Enums\RoleKey;
use App\Models\License;
use App\Models\Package;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;

abstract class TenantTestCase extends TestCase
{
    private const CONNECTION = 'tenant';

    private const DATABASE = 'hawalaty_testing_tenant';

    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pointTenantConnectionAtTestDatabase();

        if (! self::$migrated) {
            $this->createTestDatabaseIfMissing();

            Artisan::call('migrate:fresh', [
                '--path' => 'database/migrations/tenant',
                '--database' => self::CONNECTION,
                '--force' => true,
            ]);

            self::$migrated = true;
        }

        DB::connection(self::CONNECTION)->beginTransaction();

        $this->bindCurrentLicense();
    }

    /**
     * Bind the license the tenant plane reads its package features and business
     * vertical from. Pass a type to exercise a capability-gated path.
     */
    protected function bindCurrentLicense(?BusinessType $businessType = null): void
    {
        $license = new License;
        $license->business_type = $businessType ?? BusinessType::default();
        $license->setRelation('package', new Package([
            'features' => array_column(PackageFeature::cases(), 'value'),
        ]));

        app()->instance('currentLicense', $license);
    }

    protected function tearDown(): void
    {
        DB::connection(self::CONNECTION)->rollBack();

        parent::tearDown();
    }

    private function serverCredentials(): array
    {
        return [
            'host' => (string) env('DB_HOST', '127.0.0.1'),
            'port' => (string) env('DB_PORT', '3306'),
            'username' => (string) env('DB_USERNAME', 'root'),
            'password' => (string) env('DB_PASSWORD', ''),
        ];
    }

    private function pointTenantConnectionAtTestDatabase(): void
    {
        config(['database.connections.'.self::CONNECTION => [
            ...$this->serverCredentials(),
            'driver' => 'mysql',
            'database' => self::DATABASE,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ]]);

        DB::purge(self::CONNECTION);
    }

    private function createTestDatabaseIfMissing(): void
    {
        ['host' => $host, 'port' => $port, 'username' => $username, 'password' => $password] = $this->serverCredentials();

        $pdo = new PDO("mysql:host={$host};port={$port}", $username, $password);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.self::DATABASE.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    protected function seedStandardRoles(): array
    {
        $permissions = collect(PermissionKey::cases())
            ->mapWithKeys(fn (PermissionKey $key) => [$key->value => Permission::create(['key' => $key])]);

        $owner = Role::create(['key' => RoleKey::Owner, 'name' => 'Owner', 'is_system' => true]);
        $owner->permissions()->attach($permissions->pluck('id'));

        $manager = Role::create(['key' => RoleKey::Manager, 'name' => 'Manager', 'is_system' => true]);
        $manager->permissions()->attach($permissions->except(PermissionKey::TeamManage->value)->pluck('id'));

        $staff = Role::create(['key' => RoleKey::Staff, 'name' => 'Staff', 'is_system' => true]);
        $staff->permissions()->attach($permissions->get(PermissionKey::TransfersCreate->value)->id);

        return compact('owner', 'manager', 'staff');
    }
}
