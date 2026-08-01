<?php

namespace App\Services\WhatsApp;

use App\Models\Tenant\TenantSetting;
use Throwable;

class WhatsAppSettings
{
    public const ENABLED = 'whatsapp_enabled';

    public const DRIVER = 'whatsapp_driver';

    public const TOKEN = 'whatsapp_token';

    public const PHONE_NUMBER_ID = 'whatsapp_phone_number_id';

    public const WABA_ID = 'whatsapp_waba_id';

    public const APP_SECRET = 'whatsapp_app_secret';

    public const VERIFY_TOKEN = 'whatsapp_verify_token';

    public const TEMPLATE_NEW_ORDER = 'whatsapp_template_new_order';

    public const TEMPLATE_LANGUAGE = 'whatsapp_template_language';

    public const TEMPLATE_VARIABLES = 'whatsapp_template_variables';

    public const TEST_RECIPIENTS = 'whatsapp_test_recipients';

    public const COUNTRY_CODE = 'whatsapp_country_code';

    public const SECRETS = [self::TOKEN, self::APP_SECRET];

    private ?array $resolved = null;

    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $stored = [];

        try {
            $stored = TenantSetting::query()
                ->whereIn('key', array_keys($this->fallbacks()))
                ->pluck('value', 'key')
                ->all();
        } catch (Throwable) {
        }

        $resolved = [];

        foreach ($this->fallbacks() as $key => $fallback) {
            $value = $stored[$key] ?? null;
            $resolved[$key] = ($value === null || $value === '') ? $fallback : $value;
        }

        return $this->resolved = $resolved;
    }

    public function get(string $key): ?string
    {
        return $this->all()[$key] ?? null;
    }

    public function isConfigured(): bool
    {
        return filled($this->get(self::TOKEN)) && filled($this->get(self::PHONE_NUMBER_ID));
    }

    public function enabled(): bool
    {
        return filter_var($this->get(self::ENABLED), FILTER_VALIDATE_BOOLEAN);
    }

    public function driver(): string
    {
        $driver = (string) $this->get(self::DRIVER);

        if ($driver === 'cloud' && ! $this->isConfigured()) {
            return 'log';
        }

        return in_array($driver, ['log', 'cloud', 'null'], true) ? $driver : 'log';
    }

    public function put(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, $this->fallbacks())) {
                continue;
            }

            if ($value === null || $value === '') {
                TenantSetting::query()->where('key', $key)->delete();

                continue;
            }

            TenantSetting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        $this->resolved = null;
    }

    public function masked(): array
    {
        $values = $this->all();

        foreach (self::SECRETS as $key) {
            $values[$key] = filled($values[$key]) ? str_repeat('•', 12) : null;
        }

        return $values;
    }

    private function fallbacks(): array
    {
        return [
            self::ENABLED => config('whatsapp.enabled', true) ? '1' : '0',
            self::DRIVER => (string) config('whatsapp.driver', 'log'),
            self::TOKEN => config('whatsapp.token'),
            self::PHONE_NUMBER_ID => config('whatsapp.phone_number_id'),
            self::WABA_ID => config('whatsapp.waba_id'),
            self::APP_SECRET => config('whatsapp.app_secret'),
            self::VERIFY_TOKEN => config('whatsapp.verify_token'),
            self::TEMPLATE_NEW_ORDER => (string) config('whatsapp.template.new_order', 'new_order'),
            self::TEMPLATE_LANGUAGE => (string) config('whatsapp.template.language', 'ar'),
            self::TEMPLATE_VARIABLES => (string) config('whatsapp.template.variables', 8),
            self::TEST_RECIPIENTS => (string) config('whatsapp.test_recipients', ''),
            self::COUNTRY_CODE => (string) config('whatsapp.default_country_code', '972'),
        ];
    }

    public function variableCount(): int
    {
        $count = (int) $this->get(self::TEMPLATE_VARIABLES);

        return $count >= 1 ? $count : max(1, (int) config('whatsapp.template.variables', 8));
    }

    public function countryCode(): string
    {
        $code = preg_replace('/\D+/', '', (string) $this->get(self::COUNTRY_CODE)) ?? '';

        return $code !== '' ? $code : (string) config('whatsapp.default_country_code', '972');
    }

    public function testRecipients(): array
    {
        return collect(preg_split('/[,\r\n]+/', (string) $this->get(self::TEST_RECIPIENTS)))
            ->map(fn (string $value) => PhoneNumber::toE164(trim($value), $this->countryCode()))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
