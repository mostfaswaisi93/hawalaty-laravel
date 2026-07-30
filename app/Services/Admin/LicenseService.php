<?php

namespace App\Services\Admin;

use App\Enums\LicenseStatus;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\License;
use App\Models\Package;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\TeamMember;
use App\Services\TenantProvisioner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LicenseService
{
    /** How many team members the licence detail page peeks at. See show(). */
    private const TEAM_PEEK = 25;

    public function __construct(private readonly TenantProvisioner $provisioner) {}

    public function list(?string $search, ?string $status, ?string $packageId, int $perPage = 20): LengthAwarePaginator
    {
        $query = License::query()->with('package');

        $query->whereSearch(['customer_name', 'license_code'], $search);

        if ($status) {
            $query->where('status', $status);
        }

        if ($packageId) {
            $query->where('package_id', $packageId);
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    /**
     * Reuses TenantProvisioner::provision() end-to-end (license row +
     * physical database + tenant migrations + roles/owner seed) - see
     * BACKEND_PROMPT.md section 6. Not duplicated here.
     */
    public function create(array $data, ?Admin $actor): License
    {
        return $this->provisioner->provision($data, $actor);
    }

    /**
     * Runs the tenant schema migrations against a license's linked database on
     * demand - the button an operator presses after creating (and linking) a
     * cPanel database, or to roll out a newly added tenant migration to an
     * existing account. tenants:migrate is idempotent (Laravel tracks which
     * migrations ran), so pressing it on an up-to-date database is a safe no-op.
     */
    public function migrate(License $license, ?Admin $actor): void
    {
        if (! $license->databaseConnection) {
            throw new RuntimeException('لا توجد قاعدة بيانات مرتبطة بهذا الترخيص.');
        }

        $exitCode = Artisan::call('tenants:migrate', ['code' => $license->license_code]);

        if ($exitCode !== 0) {
            throw new RuntimeException('فشلت الترحيلات: '.trim(Artisan::output()));
        }

        ActivityLog::create([
            'admin_id' => $actor?->id,
            'action' => 'migrated',
            'entity_type' => 'license',
            'entity_label' => $license->customer_name,
        ]);
    }

    /**
     * Peeks at ONE tenant's team roster and recent audit activity -
     * explicitly resolving the dynamic `tenant` connection READ-ONLY for
     * this single call, then restoring whatever it pointed at before (or
     * clearing it) so the platform process never keeps the connection
     * pointed at a tenant database beyond this request - see
     * BACKEND_PROMPT.md section 8's explicit instruction on this for
     * `/admin/*` routes.
     *
     * Deliberately does NOT reuse App\Services\TenantConnectionResolver
     * here: that helper refuses to resolve a suspended/expired license (by
     * design - it's the gate tenant LOGIN must respect). An admin viewing
     * license details is a different, unconditional read: exactly the
     * suspended/expired licenses are the ones an admin most needs to
     * inspect (e.g. to check the team roster before deciding whether to
     * reactivate). So the tenant connection is wired up directly from the
     * DatabaseConnection row here, with no status check. A real bug caught
     * live during this task's verification: the naive first version reused
     * TenantConnectionResolver::resolve() and 500'd on every suspended/
     * expired license.
     *
     * @return array{license: License, team: Collection, teamTotal: int, recentActivity: Collection}
     */
    public function show(License $license): array
    {
        $license->load('package');

        $team = new Collection;
        $recentActivity = new Collection;
        $teamTotal = 0;

        $connection = $license->databaseConnection;

        if ($connection) {
            $previousTenantConfig = config('database.connections.tenant');

            try {
                config(['database.connections.tenant' => [
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

                DB::purge('tenant');

                // Capped, like the activity list beside it. This card is a PEEK
                // - "قراءة مباشرة من قاعدة بيانات المستأجر" - and an uncapped
                // get() would render every member a tenant has ever had onto an
                // admin page that exists to answer "who is on this account?".
                // `teamTotal` is what makes the cap honest: the page says how
                // many were left out instead of quietly showing a partial list.
                $teamTotal = TeamMember::query()->count();
                $team = TeamMember::query()->with('role')->orderBy('name')->take(self::TEAM_PEEK)->get();
                $recentActivity = AuditLog::query()->latest()->take(10)->get();
            } finally {
                // Restore the connection config to whatever it was before
                // this peek (the inert template from config/database.php on
                // a fresh admin request) and drop the live PDO handle to the
                // tenant database.
                config(['database.connections.tenant' => $previousTenantConfig]);
                DB::purge('tenant');
            }
        }

        return [
            'license' => $license,
            'team' => $team,
            'teamTotal' => $teamTotal,
            'recentActivity' => $recentActivity,
        ];
    }

    public function suspend(License $license, ?Admin $actor): License
    {
        return DB::transaction(function () use ($license, $actor) {
            $license->update(['status' => LicenseStatus::Suspended]);

            ActivityLog::create([
                'admin_id' => $actor?->id,
                'action' => 'suspended',
                'entity_type' => 'license',
                'entity_label' => $license->customer_name,
            ]);

            return $license;
        });
    }

    public function activate(License $license, ?Admin $actor): License
    {
        return DB::transaction(function () use ($license, $actor) {
            $license->update(['status' => LicenseStatus::Active]);

            ActivityLog::create([
                'admin_id' => $actor?->id,
                'action' => 'activated',
                'entity_type' => 'license',
                'entity_label' => $license->customer_name,
            ]);

            return $license;
        });
    }

    public function changePackage(License $license, string $packageId, ?Admin $actor): License
    {
        return DB::transaction(function () use ($license, $packageId, $actor) {
            $package = Package::findOrFail($packageId);

            $license->update(['package_id' => $package->id]);

            ActivityLog::create([
                'admin_id' => $actor?->id,
                'action' => 'package_changed',
                'entity_type' => 'license',
                'entity_label' => "{$license->customer_name} → {$package->name}",
            ]);

            return $license->fresh('package');
        });
    }
}
