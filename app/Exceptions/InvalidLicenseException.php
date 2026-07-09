<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown by App\Services\TenantConnectionResolver whenever a license code
 * cannot be used to establish a tenant database connection: the code does
 * not exist, the license is suspended/expired, or it has no provisioned
 * database_connections row yet. Callers (see
 * App\Http\Middleware\ResolveTenantConnection) are expected to catch this
 * and turn it into a 403 JSON response: {"message": "Invalid or inactive license."}
 */
class InvalidLicenseException extends Exception
{
    //
}
