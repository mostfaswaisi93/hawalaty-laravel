<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A message left through the public contact form on the landing page.
 *
 * Platform-level, like Package and License - the sender is a prospect, not a
 * tenant, so there is no tenant connection to write to.
 */
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

    /** @param  Builder<ContactMessage>  $query */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    /**
     * Unread first, then newest first - the order the index supports, and the
     * order someone working through an inbox actually wants.
     *
     * @param  Builder<ContactMessage>  $query
     */
    public function scopeForInbox(Builder $query): void
    {
        $query->orderByRaw('read_at IS NOT NULL')->latest();
    }
}
