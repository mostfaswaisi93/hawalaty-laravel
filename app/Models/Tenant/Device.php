<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

#[Fillable(['team_member_id', 'sanctum_token_id', 'name', 'browser', 'platform_icon', 'last_active_at'])]
class Device extends Model
{
    use HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'last_active_at' => 'datetime',
        ];
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    public function sanctumToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'sanctum_token_id');
    }

    public function isCurrent(?PersonalAccessToken $currentToken): bool
    {
        return $currentToken !== null && $this->sanctum_token_id === $currentToken->id;
    }
}
