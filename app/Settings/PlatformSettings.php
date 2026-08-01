<?php

namespace App\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PlatformSettings
{
    private const CACHE_KEY = 'platform.settings';

    private ?array $raw = null;

    public function __construct(private readonly SettingRegistry $registry) {}

    public function get(string $key, mixed $fallback = null): mixed
    {
        $definition = $this->registry->find($key);

        if (! $definition) {
            return $this->raw()[$key] ?? $fallback;
        }

        $stored = $this->raw()[$key] ?? null;

        if ($stored === null || $stored === '') {
            return $definition['default'];
        }

        return $definition['type']->cast($stored);
    }

    public function all(): array
    {
        $values = [];

        foreach ($this->registry->all() as $key => $definition) {
            $values[$key] = $this->get($key);
        }

        return $values;
    }

    public function unregistered(): array
    {
        return array_diff_key($this->raw(), $this->registry->all());
    }

    public function put(array $values): void
    {
        foreach ($values as $key => $value) {
            $definition = $this->registry->find($key);

            if (! $definition) {
                continue;
            }

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $definition['type']->serialize($value)],
            );
        }

        $this->flush();
    }

    public function flush(): void
    {
        $this->raw = null;
        Cache::forget(self::CACHE_KEY);
    }

    private function raw(): array
    {
        if ($this->raw !== null) {
            return $this->raw;
        }

        try {
            return $this->raw = Cache::rememberForever(
                self::CACHE_KEY,
                fn () => Setting::query()->pluck('value', 'key')->all(),
            );
        } catch (Throwable) {
            return [];
        }
    }
}
