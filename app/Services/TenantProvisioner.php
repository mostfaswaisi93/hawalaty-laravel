<?php

namespace App\Services;

use App\Enums\LicenseStatus;
use App\Enums\PermissionKey;
use App\Enums\RoleKey;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\DatabaseConnection;
use App\Models\License;
use App\Models\Package;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Models\Tenant\TeamMember;
use App\Settings\PlatformSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Given signup/admin-provisioning input, creates a fully working new tenant
 * end-to-end: license row, physical MySQL database + dedicated user, tenant
 * schema (via MigrateTenant), and the default roles/permissions/owner
 * account - see BACKEND_PROMPT.md sections 6 and 9.
 *
 * The platform-side rows (License, DatabaseConnection, ActivityLog) are
 * each written inside their own DB::transaction(); the physical database
 * itself lives on a separate MySQL connection/server and can't join a
 * Laravel transaction, so it's guarded with try/catch + manual cleanup
 * (DROP DATABASE / DROP USER / delete the rows already written) instead.
 */
class TenantProvisioner
{
    /**
     * Fallback trial length. The live value is the `trial_days` platform
     * setting (/admin/settings); this const is only the floor used when the
     * setting has never been saved. No per-package trial length exists in the
     * `packages` schema, so one value serves every package.
     */
    protected const TRIAL_DAYS = 14;

    /**
     * @param  array{businessName:string, ownerName:string, ownerEmail:string, phone?:string, password:string, packageId?:string}  $data
     *
     * @throws Throwable
     */
    public function provision(array $data, ?Admin $actor = null): License
    {
        $package = isset($data['packageId'])
            ? Package::findOrFail($data['packageId'])
            : Package::orderBy('price')->firstOrFail();

        $license = null;
        $connection = null;
        // Only set in AUTO mode, so cleanup() DROPs only a database THIS class
        // created - never the operator's own cPanel database in link mode.
        $dbName = null;
        $dbUser = null;

        // Link mode: the operator created the database in cPanel (shared hosting
        // forbids CREATE DATABASE from the app) and is linking its credentials.
        // Absent -> fall back to creating the database automatically, which is
        // what self-service signup and a full-privilege host still use.
        $linkMode = filled($data['dbName'] ?? null);

        try {
            $license = DB::transaction(fn () => License::create([
                'license_code' => $this->generateLicenseCode(),
                'customer_name' => $data['businessName'],
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

                // Fail fast with a clear message if the credentials or grants
                // are wrong, BEFORE migrating against an unreachable database.
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

    /**
     * 8-char uppercase alphanumeric code, short and human-typeable (e.g. as
     * a code read over the phone or typed on a login screen), unique
     * against existing licenses. Str::random() only ever produces [A-Za-z0-9]
     * (see Illuminate\Support\Str), so the uppercased result is guaranteed
     * safe to also reuse (lowercased) as a MySQL identifier fragment below.
     */
    /**
     * Trial length from platform settings, falling back to TRIAL_DAYS. Resolved
     * from the container rather than injected so the existing constructor
     * signature (and every caller of it) stays untouched.
     */
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

    /**
     * Actually creates a real physical MySQL database + dedicated user on
     * the platform's own MySQL server (`mysql` connection) - see
     * BACKEND_PROMPT.md section 6, "default/automatic mode". Database name
     * and username are derived from the license code (already a
     * server-generated random alnum string, never user input), lowercased;
     * still whitelist-validated before interpolation into DDL/DCL
     * statements since MySQL doesn't support bound parameters for
     * identifiers.
     *
     * @return array{0: string, 1: string, 2: string} [databaseName, username, password]
     */
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

        // '%' وحده لا يكفي. مع skip_name_resolve=OFF يترجم الخادم عنوان
        // 127.0.0.1 عكسياً إلى 'localhost'، وعندها يفوز أي حساب أخص على
        // البدل — وحزم MAMP/XAMPP تأتي بمستخدم مجهول ''@'localhost' يخطف
        // الاتصال ويُفشل المصادقة بـ:
        //   Access denied for user 'user_xxx'@'localhost'
        // إنشاء الحساب لكلا المضيفين يجعل التزويد يعمل عبر TCP و socket معاً.
        foreach (['%', 'localhost', '127.0.0.1'] as $host) {
            DB::connection('mysql')->statement("CREATE USER IF NOT EXISTS '{$dbUser}'@'{$host}' IDENTIFIED BY '{$dbPassword}'");
            DB::connection('mysql')->statement("GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'{$host}'");
        }

        DB::connection('mysql')->statement('FLUSH PRIVILEGES');

        return [$dbName, $dbUser, $dbPassword];
    }

    /**
     * Proves the operator-supplied credentials actually reach the database
     * BEFORE any migration is attempted, so a typo or a missing GRANT in cPanel
     * surfaces as one clear message instead of a half-migrated database. Uses a
     * throwaway `tenant_probe` connection so the live `tenant` config is left
     * untouched, and never echoes the raw PDO error (it leaks host/user and is
     * not actionable to the operator).
     */
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

    /**
     * Switches the dynamic `tenant` connection to the just-provisioned
     * database and seeds the 7 fixed permissions, the 3 system roles
     * (owner = all permissions, manager = all except team.manage, staff =
     * transfers.create only), and the owner's TeamMember login.
     */
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
    }

    /**
     * Best-effort teardown of everything provisioned so far when a later
     * step fails: drop the physical database/user (each wrapped so one
     * failure doesn't block the other), then delete the platform rows
     * already committed. Never throws - the original exception from
     * provision() is what the caller should see.
     */
    protected function cleanup(?string $dbName, ?string $dbUser, ?DatabaseConnection $connection, ?License $license): void
    {
        if ($dbName) {
            try {
                DB::connection('mysql')->statement("DROP DATABASE IF EXISTS `{$dbName}`");
            } catch (Throwable) {
                // best effort
            }
        }

        if ($dbUser) {
            // يجب أن يطابق مجموعة المضيفين في provisionPhysicalDatabase()،
            // وإلا بقي حساب يتيم يمنع إعادة التزويد بنفس رمز الترخيص.
            foreach (['%', 'localhost', '127.0.0.1'] as $host) {
                try {
                    DB::connection('mysql')->statement("DROP USER IF EXISTS '{$dbUser}'@'{$host}'");
                } catch (Throwable) {
                    // best effort
                }
            }
        }

        $connection?->delete();
        $license?->delete();
    }
}
