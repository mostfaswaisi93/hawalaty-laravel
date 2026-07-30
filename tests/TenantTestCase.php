<?php

namespace Tests;

use App\Enums\PermissionKey;
use App\Enums\RoleKey;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Base class for tests that exercise tenant-space models/services (Transfer,
 * Bank, Customer, TeamMember, MenuItem, ...). These all live on the dynamic
 * `tenant` connection (see App\Services\TenantConnectionResolver), which is
 * always a real MySQL database - several tenant migrations use MySQL-only
 * DDL (enum columns, foreign keys with named actions), so they cannot run
 * against the sqlite `testing` connection the rest of the suite uses (see
 * tests/Feature/WhatsApp/WhatsAppSenderTest.php's class docblock for the
 * same constraint).
 *
 * Points the `tenant` connection at a dedicated, disposable MySQL database
 * (never a real tenant's), migrates it once per test run, and wraps every
 * test in its own transaction that is rolled back afterward - the same
 * isolation RefreshDatabase gives the sqlite-backed tests, just done by hand
 * because migrate:fresh needs to target a non-default connection.
 */
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
    }

    protected function tearDown(): void
    {
        DB::connection(self::CONNECTION)->rollBack();

        parent::tearDown();
    }

    /**
     * Where the MySQL server is, for both the connection and the CREATE
     * DATABASE below.
     *
     * These were hardcoded to 127.0.0.1/root/no-password, which is the Laragon
     * default and therefore worked on the machine it was written on and nowhere
     * else: anyone whose MySQL root has a password could not run the tenant
     * half of the suite at all, and the DB_* variables CI sets were doing
     * nothing. The old values remain the defaults, so a Laragon checkout still
     * needs no configuration.
     *
     * `DB_DATABASE` is deliberately NOT read - the tenant database is created
     * and wiped by this harness, so it must never be pointed at a database the
     * developer chose for something else.
     *
     * @return array{host: string, port: string, username: string, password: string}
     */
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

    /**
     * Seeds the same 7 permissions + 3 system roles (owner: everything,
     * manager: everything but team.manage, staff: transfers.create only)
     * that TenantProvisioner::seedTenant() creates for a real new tenant.
     *
     * @return array{owner: Role, manager: Role, staff: Role}
     */
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
