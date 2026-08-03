<?php

namespace App\Services\Tenant;

use App\Models\Tenant\TenantSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class BusinessLogo
{
    public const KEY = 'business_logo';

    public const MAX_KILOBYTES = 1024;

    private const DIRECTORY = 'logos';

    public function put(UploadedFile $file): string
    {
        $this->delete();

        $name = Str::ulid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs(self::DIRECTORY, $name, 'public');

        TenantSetting::updateOrCreate(['key' => self::KEY], ['value' => $path]);

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
            return null;
        }
    }
}
