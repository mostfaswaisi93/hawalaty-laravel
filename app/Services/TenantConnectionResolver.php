<?php

namespace App\Services;

use App\Enums\LicenseStatus;
use App\Exceptions\InvalidLicenseException;
use App\Models\License;
use Illuminate\Support\Facades\DB;

class TenantConnectionResolver
{
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
