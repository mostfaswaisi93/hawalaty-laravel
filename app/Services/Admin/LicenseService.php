<?php

namespace App\Services\Admin;

use App\Enums\BusinessType;
use App\Enums\LicenseStatus;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\License;
use App\Models\Package;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\ConstantList;
use App\Models\Tenant\TeamMember;
use App\Services\Tenant\CatalogNaming;
use App\Services\TenantProvisioner;
use Closure;
use Throwable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LicenseService
{
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

    public function create(array $data, ?Admin $actor): License
    {
        return $this->provisioner->provision($data, $actor);
    }

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

    public function show(License $license): array
    {
        $license->load('package');

        $team = new Collection;
        $recentActivity = new Collection;
        $teamTotal = 0;

        $this->onTenant($license, function () use (&$team, &$teamTotal, &$recentActivity) {
            $teamTotal = TeamMember::query()->count();
            $team = TeamMember::query()->with('role')->orderBy('name')->take(self::TEAM_PEEK)->get();
            $recentActivity = AuditLog::query()->latest()->take(10)->get();
        });

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

    /**
     * Retarget a license at a different vertical.
     *
     * Capabilities only gate what is shown, never what is stored, so switching is
     * non-destructive: modules the new vertical drops keep their rows and simply
     * stop being reachable, and modules it adds open empty. The only side effect
     * is seeding the constant lists the new trade needs; existing lists and the
     * tenant's own catalog naming are left alone.
     */
    public function changeBusinessType(License $license, BusinessType $type, ?Admin $actor): BusinessType
    {
        $previous = $license->businessType();

        DB::transaction(function () use ($license, $type, $previous, $actor) {
            $license->update(['business_type' => $type]);

            ActivityLog::create([
                'admin_id' => $actor?->id,
                'action' => 'business_type_changed',
                'entity_type' => 'license',
                'entity_label' => "{$license->customer_name}: {$previous->label()} → {$type->label()}",
            ]);
        });

        try {
            $this->onTenant($license, function () use ($type, $previous) {
                foreach ($type->constantLists() as $key => $title) {
                    ConstantList::firstOrCreate(['key' => $key], ['title' => $title]);
                }

                $this->retitleCatalog($previous, $type);
            });
        } catch (Throwable) {
            // The vertical has already changed on the platform side, which is the
            // part that gates the modules. A workspace that is unreachable right
            // now just opens without the seeded lists; they are firstOrCreate, so
            // a later change or migration fills them in.
        }

        return $type;
    }

    /**
     * Move the catalog wording onto the new vertical, but only where the tenant
     * never touched it.
     *
     * A workspace still labelled with the old trade's words after a switch reads
     * as a bug; a workspace that loses wording the owner deliberately chose reads
     * as data loss. Comparing against the previous vertical's defaults tells the
     * two apart.
     */
    private function retitleCatalog(BusinessType $previous, BusinessType $type): void
    {
        $naming = app(CatalogNaming::class);
        $was = $previous->naming();
        $now = $type->naming();

        $changes = [];

        foreach ([
            CatalogNaming::CATALOG => 'catalog',
            CatalogNaming::CATEGORY => 'category',
            CatalogNaming::ITEM => 'item',
        ] as $key => $slot) {
            $stored = $naming->raw($key);

            if ($stored === null || $stored === $was[$slot]) {
                $changes[$key] = $now[$slot];
            }
        }

        if ($naming->raw(CatalogNaming::ICON) === null || $naming->icon() === $previous->icon()) {
            $changes[CatalogNaming::ICON] = $type->icon();
        }

        if ($changes !== []) {
            $naming->put($changes);
        }
    }

    /**
     * Run a closure with the `tenant` connection pointed at this license, then put
     * the previous target back. The admin plane is mid-request on the platform
     * database, so the swap has to be reversed even when the closure throws.
     */
    private function onTenant(License $license, Closure $work): void
    {
        $connection = $license->databaseConnection;

        if (! $connection) {
            return;
        }

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

            $work();
        } finally {
            config(['database.connections.tenant' => $previousTenantConfig]);
            DB::purge('tenant');
        }
    }
}
