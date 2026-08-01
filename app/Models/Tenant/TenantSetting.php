<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value'])]
class TenantSetting extends Model
{
    use HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }
}
