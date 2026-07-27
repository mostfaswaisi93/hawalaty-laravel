<?php

namespace App\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * The single way the application reads a platform setting.
 *
 * Settings are read on pages that render on nearly every request (the admin
 * dashboard, the public help/privacy/terms pages), so the whole table is
 * loaded once and cached rather than queried per key. Any write flushes it.
 *
 * Registered as a singleton in AppServiceProvider, so within one request the
 * table is touched at most once even on a cache miss.
 */
class PlatformSettings
{
    private const CACHE_KEY = 'platform.settings';

    /** @var array<string, string|null>|null */
    private ?array $raw = null;

    public function __construct(private readonly SettingRegistry $registry) {}

    /**
     * A setting's value, cast to its declared type, falling back to the
     * registry default when unset or blank. Unknown keys return $fallback.
     */
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

    /**
     * Every registered setting, cast, with defaults filled in - what the
     * settings form binds to.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $values = [];

        foreach ($this->registry->all() as $key => $definition) {
            $values[$key] = $this->get($key);
        }

        return $values;
    }

    /**
     * Keys stored in the database that the registry does not know about -
     * surfaced separately on the settings page so legacy or hand-added keys are
     * never silently hidden.
     *
     * @return array<string, string|null>
     */
    public function unregistered(): array
    {
        return array_diff_key($this->raw(), $this->registry->all());
    }

    /**
     * Bulk-saves registered settings. Values are serialised through their
     * declared type, so a boolean stores "1"/"0" rather than "on".
     *
     * @param  array<string, mixed>  $values
     */
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

    /**
     * @return array<string, string|null>
     */
    private function raw(): array
    {
        return $this->raw ??= Cache::rememberForever(
            self::CACHE_KEY,
            fn () => Setting::query()->pluck('value', 'key')->all(),
        );
    }
}
