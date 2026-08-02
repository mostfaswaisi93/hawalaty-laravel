<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Another number that names the same part.
 *
 * The customer arrives holding the old one with something stamped on it. Whether
 * that is the vehicle manufacturer's number or an aftermarket brand's, it has to
 * find the part.
 */
#[Fillable(['menu_item_id', 'type', 'reference', 'brand'])]
class ItemReference extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function typeLabel(): string
    {
        return $this->type === 'oe' ? __('رقم الوكالة') : __('رقم بديل');
    }

    /**
     * Numbers are printed with spaces, dashes and dots that nobody types the
     * same way twice. Comparison happens on the stripped form.
     */
    public static function normalise(string $reference): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $reference) ?? '');
    }
}
