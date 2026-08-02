<?php

namespace App\Services;

use App\Enums\BusinessType;
use App\Enums\LicenseStatus;
use App\Enums\PermissionKey;
use App\Enums\RoleKey;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\DatabaseConnection;
use App\Models\License;
use App\Models\Package;
use App\Models\Tenant\ConstantList;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Models\Tenant\TeamMember;
use App\Services\Tenant\CatalogNaming;
use App\Settings\PlatformSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TenantProvisioner
{
    protected const TRIAL_DAYS = 14;

    public function provision(array $data, ?Admin $actor = null): License
    {
        $package = isset($data['packageId'])
            ? Package::findOrFail($data['packageId'])
            : Package::orderBy('price')->firstOrFail();

        $businessType = BusinessType::tryFrom((string) ($data['businessType'] ?? '')) ?? BusinessType::default();

        $license = null;
        $connection = null;

        $dbName = null;
        $dbUser = null;

        $linkMode = filled($data['dbName'] ?? null);

        try {
            $license = DB::transaction(fn () => License::create([
                'license_code' => $this->generateLicenseCode(),
                'customer_name' => $data['businessName'],

                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'business_type' => $businessType,
                'package_id' => $package->id,
                'status' => LicenseStatus::Trial,
                'expired_at' => now()->addDays($this->trialDays()),
                'customer_balance' => 0,
            ]));

            if ($linkMode) {
                $connection = DB::transaction(fn () => DatabaseConnection::create([
                    'license_id' => $license->id,
                    'host' => filled($data['dbHost'] ?? null) ? $data['dbHost'] : '127.0.0.1',
                    'port' => (int) ($data['dbPort'] ?? 0) ?: 3306,
                    'database_name' => $data['dbName'],
                    'username' => $data['dbUsername'],
                    'password' => $data['dbPassword'],
                ]));

                $this->assertConnectable($connection);
            } else {
                [$dbName, $dbUser, $dbPassword] = $this->provisionPhysicalDatabase($license->license_code);

                $connection = DB::transaction(fn () => DatabaseConnection::create([
                    'license_id' => $license->id,
                    'host' => '127.0.0.1',
                    'port' => 3306,
                    'database_name' => $dbName,
                    'username' => $dbUser,
                    'password' => $dbPassword,
                ]));
            }

            $exitCode = Artisan::call('tenants:migrate', ['code' => $license->license_code]);

            if ($exitCode !== 0) {
                throw new RuntimeException("tenants:migrate failed for {$license->license_code}: ".Artisan::output());
            }

            $this->seedTenant($license, $data);

            DB::transaction(fn () => ActivityLog::create([
                'admin_id' => $actor?->id,
                'action' => 'created',
                'entity_type' => 'license',
                'entity_label' => $license->customer_name,
            ]));

            return $license;
        } catch (Throwable $e) {
            $this->cleanup($dbName, $dbUser, $connection, $license);

            throw $e;
        }
    }

    protected function trialDays(): int
    {
        $configured = app(PlatformSettings::class)->get('trial_days');

        return is_int($configured) && $configured > 0 ? $configured : self::TRIAL_DAYS;
    }

    protected function generateLicenseCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (License::where('license_code', $code)->exists());

        return $code;
    }

    protected function provisionPhysicalDatabase(string $licenseCode): array
    {
        $suffix = strtolower($licenseCode);
        $dbName = 'license_'.$suffix;
        $dbUser = 'user_'.$suffix;
        $dbPassword = Str::random(32);

        if (! preg_match('/^[a-z0-9_]{1,60}$/', $dbName) || ! preg_match('/^[a-z0-9_]{1,32}$/', $dbUser)) {
            throw new RuntimeException('Generated tenant database identifiers failed the safety whitelist check.');
        }

        DB::connection('mysql')->statement("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        foreach (['%', 'localhost', '127.0.0.1'] as $host) {
            DB::connection('mysql')->statement("CREATE USER IF NOT EXISTS '{$dbUser}'@'{$host}' IDENTIFIED BY '{$dbPassword}'");
            DB::connection('mysql')->statement("GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'{$host}'");
        }

        DB::connection('mysql')->statement('FLUSH PRIVILEGES');

        return [$dbName, $dbUser, $dbPassword];
    }

    protected function assertConnectable(DatabaseConnection $connection): void
    {
        config(['database.connections.tenant_probe' => [
            'driver' => 'mysql',
            'host' => $connection->host,
            'port' => $connection->port,
            'database' => $connection->database_name,
            'username' => $connection->username,
            'password' => $connection->password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ]]);

        try {
            DB::connection('tenant_probe')->select('SELECT 1');
        } catch (Throwable) {
            throw new RuntimeException('تعذّر الاتصال بقاعدة البيانات المُدخَلة. تأكّد من اسم القاعدة والمستخدم وكلمة المرور ومنح كامل الصلاحيات للمستخدم على القاعدة في cPanel.');
        } finally {
            DB::purge('tenant_probe');
        }
    }

    protected function seedTenant(License $license, array $data): void
    {
        TenantConnectionResolver::resolve($license->license_code);
        app()->instance('currentLicense', $license);

        $permissions = collect(PermissionKey::cases())
            ->mapWithKeys(fn (PermissionKey $key) => [$key->value => Permission::create(['key' => $key])]);

        $ownerRole = Role::create(['key' => RoleKey::Owner, 'name' => 'Owner', 'is_system' => true]);
        $ownerRole->permissions()->attach($permissions->pluck('id'));

        $managerRole = Role::create(['key' => RoleKey::Manager, 'name' => 'Manager', 'is_system' => true]);
        $managerRole->permissions()->attach(
            $permissions->except(PermissionKey::TeamManage->value)->pluck('id')
        );

        $staffRole = Role::create(['key' => RoleKey::Staff, 'name' => 'Staff', 'is_system' => true]);
        $staffRole->permissions()->attach($permissions->get(PermissionKey::TransfersCreate->value)->id);

        TeamMember::create([
            'name' => $data['ownerName'],
            'email' => $data['ownerEmail'],
            'password' => $data['password'],
            'role_id' => $ownerRole->id,
            'active' => true,
        ]);

        $this->seedVerticalDefaults($license->businessType());
    }

    /**
     * Open the workspace with the terminology and constant lists its trade uses,
     * so the owner is not asked to invent vocabulary the vertical already has.
     */
    protected function seedVerticalDefaults(BusinessType $businessType): void
    {
        $naming = $businessType->naming();

        app(CatalogNaming::class)->put([
            CatalogNaming::CATALOG => $naming['catalog'],
            CatalogNaming::CATEGORY => $naming['category'],
            CatalogNaming::ITEM => $naming['item'],
            CatalogNaming::ICON => $businessType->icon(),
        ]);

        foreach ($businessType->constantLists() as $key => $title) {
            ConstantList::firstOrCreate(['key' => $key], ['title' => $title]);
        }
    }

    protected function cleanup(?string $dbName, ?string $dbUser, ?DatabaseConnection $connection, ?License $license): void
    {
        if ($dbName) {
            try {
                DB::connection('mysql')->statement("DROP DATABASE IF EXISTS `{$dbName}`");
            } catch (Throwable) {
            }
        }

        if ($dbUser) {
            foreach (['%', 'localhost', '127.0.0.1'] as $host) {
                try {
                    DB::connection('mysql')->statement("DROP USER IF EXISTS '{$dbUser}'@'{$host}'");
                } catch (Throwable) {
                }
            }
        }

        $connection?->delete();
        $license?->delete();
    }
}
