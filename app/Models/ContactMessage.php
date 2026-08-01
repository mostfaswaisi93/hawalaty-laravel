<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'email', 'phone', 'company', 'subject', 'message', 'plan', 'ip_address', 'locale'])]
class ContactMessage extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    public function scopeForInbox(Builder $query): void
    {
        $query->orderByRaw('read_at IS NOT NULL')->latest();
    }
}
