<?php

namespace App\Services\Admin;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;

class SettingService
{
    public function list(): Collection
    {
        return Setting::query()->orderBy('key')->get();
    }

    public function upsert(string $key, ?string $value): Setting
    {
        return Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public function forget(string $key): void
    {
        Setting::query()->where('key', $key)->delete();
    }
}
