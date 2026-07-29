<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken;

/**
 * Sanctum's personal_access_tokens model, made connection-aware - see
 * BACKEND_PROMPT.md section 8.1 and the reasoning in the task report.
 *
 * Sanctum only supports ONE globally-registered personal access token
 * model class (Sanctum::$personalAccessTokenModel is a single static). That
 * class is used for every guard, including both the Super Admin portal
 * (Admin, platform connection) and every tenant's team members (tenant
 * connection). A single hardcoded `$connection` would break one side or the
 * other, so the connection is resolved dynamically, per instance, at query
 * time: if App\Http\Middleware\ResolveTenantConnection has already run for
 * this request (it always runs before `auth:sanctum` on tenant routes, and
 * never runs at all on admin routes), the `currentLicense` binding will be
 * present in the container and we use the `tenant` connection it configured;
 * otherwise we fall back to the platform's own `mysql` connection, which is
 * exactly where Admin's tokens live.
 */
class TenantPersonalAccessToken extends PersonalAccessToken
{
    /**
     * Both the platform DB and every tenant DB name this table
     * `personal_access_tokens` (see the migrations in both
     * database/migrations/ and database/migrations/tenant/); without this,
     * Eloquent would infer `tenant_personal_access_tokens` from the class
     * name and fail on both connections.
     */
    protected $table = 'personal_access_tokens';

    /**
     * The platform side returns the app's CONFIGURED default rather than the
     * literal string 'mysql'.
     *
     * Naming the connection hardcoded the deployment into the model: it is
     * correct only while the platform connection happens to be called `mysql`,
     * and breaks silently the moment `DB_CONNECTION` is anything else - which
     * is exactly what the test environment is. Admin token creation could
     * therefore never be exercised: `createToken()` went looking for a MySQL
     * server and a database literally named ":memory:". The tenant side still
     * names `tenant` explicitly, because that connection is built per request
     * by ResolveTenantConnection and is never the default.
     */
    public function getConnectionName()
    {
        return app()->bound('currentLicense') ? 'tenant' : config('database.default');
    }
}
