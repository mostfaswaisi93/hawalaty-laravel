<?php

namespace App\Services\WhatsApp;

use App\Models\Tenant\TenantSetting;
use Throwable;

/**
 * This tenant's own WhatsApp Cloud API credentials.
 *
 * config/whatsapp.php reads from .env, which is process-global: one set of
 * credentials for every tenant on the box. That is wrong for this product -
 * each business owns its own Meta app, its own number and its own bill, and no
 * tenant may send from (or be billed for) another's number. So the credentials
 * move into the tenant's own database, keyed in `tenant_settings` alongside the
 * catalogue labels (see App\Services\Tenant\CatalogNaming, same pattern).
 *
 * .env stays as the FALLBACK rather than being deleted: a single-tenant install
 * and the existing test rigs keep working untouched, and a tenant that has not
 * filled the form yet inherits whatever the operator configured globally.
 *
 * Resolved once per request and degrades to config on any error - an
 * unreachable settings table must never 500 the order screen, which is the same
 * promise WhatsAppSender makes.
 */
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

    /** How many {{n}} the chosen template has - see variableCount(). */
    public const TEMPLATE_VARIABLES = 'whatsapp_template_variables';

    /**
     * Meta's allow-list for a TEST number: it refuses every recipient not
     * registered in the console (error 131030), capped at five. Stored so the
     * send form can offer them as one-click chips instead of the operator
     * retyping a number and discovering the rejection only after sending.
     * Irrelevant once a real number is in use - leave it empty then.
     */
    public const TEST_RECIPIENTS = 'whatsapp_test_recipients';

    /**
     * Country code prepended to a bare local number (0599302930). Per tenant
     * because 972 and 970 both serve the same streets here: a tenant whose
     * numbers are registered at Meta under +970 must not have them rewritten
     * to +972, which Meta then rejects as a different - unallowed - number.
     */
    public const COUNTRY_CODE = 'whatsapp_country_code';

    /** Written to the form but never echoed back - see masked(). */
    public const SECRETS = [self::TOKEN, self::APP_SECRET];

    /** @var array<string, string|null>|null */
    private ?array $resolved = null;

    /**
     * Every key with its effective value: tenant row, else config/.env.
     *
     * @return array<string, string|null>
     */
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
            // Table missing or connection down: fall back silently. Logging here
            // would fire on every request of a tenant that never configured it.
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

    /** Whether this tenant can actually reach Meta with what it has. */
    public function isConfigured(): bool
    {
        return filled($this->get(self::TOKEN)) && filled($this->get(self::PHONE_NUMBER_ID));
    }

    public function enabled(): bool
    {
        return filter_var($this->get(self::ENABLED), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * `cloud` is only honoured once credentials exist, so a tenant that flips
     * the driver before filling the token still gets the safe log path instead
     * of a run of failed sends.
     */
    public function driver(): string
    {
        $driver = (string) $this->get(self::DRIVER);

        if ($driver === 'cloud' && ! $this->isConfigured()) {
            return 'log';
        }

        return in_array($driver, ['log', 'cloud', 'null'], true) ? $driver : 'log';
    }

    /**
     * Persists only the keys present in $values. A null or '' clears the tenant
     * row so the key falls back to .env again rather than storing an empty
     * string that would shadow it.
     *
     * @param  array<string, string|null>  $values
     */
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

    /**
     * Safe shape for the settings form: secrets become a fixed placeholder so
     * the page never re-renders a token into HTML, while still showing the
     * operator that one is stored.
     *
     * @return array<string, string|null>
     */
    public function masked(): array
    {
        $values = $this->all();

        foreach (self::SECRETS as $key) {
            $values[$key] = filled($values[$key]) ? str_repeat('•', 12) : null;
        }

        return $values;
    }

    /** @return array<string, string|null> key => value inherited from config/.env */
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

    /**
     * The number of {{n}} the chosen template expects - so WhatsAppSender sends
     * exactly that many parameters. Never below 1 (a template with no parameter
     * has nowhere to put the order), and falls back to the config default.
     */
    public function variableCount(): int
    {
        $count = (int) $this->get(self::TEMPLATE_VARIABLES);

        return $count >= 1 ? $count : max(1, (int) config('whatsapp.template.variables', 8));
    }

    /** Digits only - anything else would corrupt every normalised number. */
    public function countryCode(): string
    {
        $code = preg_replace('/\D+/', '', (string) $this->get(self::COUNTRY_CODE)) ?? '';

        return $code !== '' ? $code : (string) config('whatsapp.default_country_code', '972');
    }

    /**
     * The allow-listed numbers as a clean list, normalised for display.
     * Accepts commas or newlines so pasting from the Meta console just works.
     *
     * @return array<int, string>
     */
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
