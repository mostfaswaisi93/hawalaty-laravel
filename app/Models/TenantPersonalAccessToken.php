<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken;

class TenantPersonalAccessToken extends PersonalAccessToken
{
    protected $table = 'personal_access_tokens';

    public function getConnectionName()
    {
        return app()->bound('currentLicense') ? 'tenant' : config('database.default');
    }
}
