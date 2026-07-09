<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Real connection details for one license's tenant database — see
 * BACKEND_PROMPT.md section 3. `password` is encrypted at rest via Laravel's
 * native `encrypted` cast; it is never plain text in the `database_connections`
 * table, only decrypted in memory when Eloquent hydrates the attribute.
 */
#[Fillable(['license_id', 'host', 'port', 'database_name', 'username', 'password'])]
#[Hidden(['password'])]
class DatabaseConnection extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'password' => 'encrypted',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
