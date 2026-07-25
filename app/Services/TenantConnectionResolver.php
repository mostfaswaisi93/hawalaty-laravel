<?php

namespace App\Services;

use App\Enums\LicenseStatus;
use App\Exceptions\InvalidLicenseException;
use App\Models\License;
use Illuminate\Support\Facades\DB;

/**
 * Resolves a license code to its tenant database and points Laravel's
 * dynamic `tenant` connection at it - see BACKEND_PROMPT.md section 8.1.
 *
 * This is the single chokepoint every tenant-aware request (or the login
 * controller, which has no token yet and calls this directly with the
 * license code from the request body) must pass through before running any
 * query against a tenant's data.
 */
class TenantConnectionResolver
{
    /**
     * Resolve the given license code, point the `tenant` connection at its
     * database, and return the License model (callers need it - e.g. the
     * login controller needs the license id, ResolveTenantConnection binds
     * it into the container for later use).
     *
     * @throws InvalidLicenseException
     */
    public static function resolve(string $licenseCode): License
    {
        $license = License::where('license_code', $licenseCode)->first();

        if (! $license) {
            throw new InvalidLicenseException('Invalid or inactive license.');
        }

        if (in_array($license->status, [LicenseStatus::Suspended, LicenseStatus::Expired], true)) {
            throw new InvalidLicenseException('Invalid or inactive license.');
        }

        $connection = $license->databaseConnection;

        if (! $connection) {
            throw new InvalidLicenseException('Invalid or inactive license.');
        }

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

        return $license;
    }
}
