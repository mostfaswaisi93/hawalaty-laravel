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

                $teamTotal = TeamMember::query()->count();
                $team = TeamMember::query()->with('role')->orderBy('name')->take(self::TEAM_PEEK)->get();
                $recentActivity = AuditLog::query()->latest()->take(10)->get();
            } finally {
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
