<?php

namespace App\Services\Tenant;

use App\Models\Tenant\TenantSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The business's own logo, printed at the top of its receipts.
 *
 * The FILE lives on the `public` disk; only its path is kept in the tenant's
 * settings, so the row stays small and the image is replaceable without a
 * migration.
 *
 * Reading it back returns a DATA URI rather than a URL. That is deliberate:
 *   - the receipt is also rendered by dompdf for the mobile API, which cannot
 *     fetch an authenticated URL;
 *   - `public/storage` is a symlink that shared hosting frequently does not
 *     have, and a receipt whose logo silently 404s is worse than none.
 * A data URI needs neither, and a logo is small enough to inline.
 */
class BusinessLogo
{
    public const KEY = 'business_logo';

    /** Big enough for a crisp receipt header, small enough to inline. */
    public const MAX_KILOBYTES = 1024;

    private const DIRECTORY = 'logos';

    /** Stores the upload, replacing whatever was there before. */
    public function put(UploadedFile $file): string
    {
        $this->delete();

        $name = Str::ulid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs(self::DIRECTORY, $name, 'public');

        TenantSetting::updateOrCreate([ 'key' => self::KEY], ['value' => $path]);

        return $path;
    }

    public function delete(): void
    {
        $path = $this->path();

        if ($path !== null && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        TenantSetting::where('key', self::KEY)->delete();
    }

    public function exists(): bool
    {
        $path = $this->path();

        return $path !== null && Storage::disk('public')->exists($path);
    }

    /**
     * The logo as a `data:` URI, or null when there is none. Degrades to null
     * if the tenant connection or the file is unreachable - a missing logo must
     * never take a receipt down.
     */
    public function dataUri(): ?string
    {
        try {
            $path = $this->path();

            if ($path === null || ! Storage::disk('public')->exists($path)) {
                return null;
            }

            $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($path));
        } catch (Throwable) {
            return null;
        }
    }

    private function path(): ?string
    {
        try {
            return TenantSetting::where('key', self::KEY)->value('value');
        } catch (Throwable) {
            // No tenant connection, or the table has not been migrated yet.
            return null;
        }
    }
}
