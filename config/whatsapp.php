<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp order notifications
    |--------------------------------------------------------------------------
    |
    | Consumed by App\Services\WhatsApp\WhatsAppSender. When a bank/wallet
    | order is created, a message is sent to the WhatsApp number stored on the
    | selected bank (`banks.phone`).
    |
    | The default driver is `log` on purpose: a fresh clone with no credentials
    | renders the real Arabic message into storage/logs/laravel.log (watch it
    | with `php artisan pail`), so the feature is demonstrably working with zero
    | setup and nothing leaves the machine. Set WHATSAPP_DRIVER=cloud once real
    | Meta Cloud API credentials exist.
    |
    */

    'enabled' => env('WHATSAPP_ENABLED', true),

    // log | cloud | null. An unknown value falls back to `log` rather than
    // throwing - a typo in .env must never 500 the order screen.
    'driver' => env('WHATSAPP_DRIVER', 'log'),

    // No defaults for credentials - a secret must never be baked into config.
    'token' => env('WHATSAPP_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com'),

    // Needed to verify the X-Hub-Signature-256 on inbound webhook calls and
    // to echo hub.challenge back when Meta registers the callback URL. Like
    // the credentials above these are per-tenant in practice - see
    // App\Services\WhatsApp\WhatsAppSettings - and these entries are only the
    // fallback for a single-tenant install.
    'waba_id' => env('WHATSAPP_WABA_ID'),
    'app_secret' => env('WHATSAPP_APP_SECRET'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),

    /*
    | Meta rejects free-form text sent outside a 24-hour customer-initiated
    | window (error 131047). A bank's number never initiates a conversation, so
    | the `cloud` driver ALWAYS sends an approved template. The template's body
    | carries the layout as STATIC text (its line breaks are what make the
    | message readable) with one positional parameter per order field -
    | {{1}}..{{8}}, matching App\Services\WhatsApp\NewOrderMessage::parameters()
    | in order and count. Each parameter is ONE line: Meta rejects a newline
    | inside a parameter with error 132018, and a count mismatch with 132000.
    */
    'template' => [
        'new_order' => env('WHATSAPP_TEMPLATE_NEW_ORDER', 'new_order'),
        'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'ar'),

        // How many {{n}} the chosen template has, so the code sends the matching
        // number of parameters. 1 = a legacy single-parameter template (the whole
        // order goes into {{1}} as one line); the multi-field layout uses
        // NewOrderMessage::PARAM_COUNT. Per-tenant override lives in
        // tenant_settings (whatsapp_template_variables).
        'variables' => (int) env('WHATSAPP_TEMPLATE_VARIABLES', 8),
    ],

    // Deliberately short: this call happens inside the order-creation request.
    'connect_timeout' => (int) env('WHATSAPP_CONNECT_TIMEOUT', 3),
    'timeout' => (int) env('WHATSAPP_TIMEOUT', 5),

    // Suppresses a duplicate message when the operator double-submits the order
    // form or an API client retries. Needs a shared cache store to work across
    // requests (CACHE_STORE=database does); under the `array` store it quietly
    // degrades to a no-op, which risks a duplicate message but never a lost or
    // corrupted order.
    'dedupe_ttl' => (int) env('WHATSAPP_DEDUPE_TTL', 3600),

    /*
    | 972 = Israel, 970 = Palestine. Applied ONLY to a bare local number that
    | carries no country code of its own (e.g. 0595817016). A number that
    | already states its country code is never rewritten.
    |
    | `country_codes` is the set used to collapse a leftover trunk zero, which
    | repairs the common paste error of prefixing the country code without
    | dropping the local leading 0: +9720595817016 -> +972595817016.
    */
    // قائمة أرقام الاختبار المسموح بها عند ميتا (الرقم التجريبي لا يرسل
    // لغيرها). فواصل أو أسطر. تُترك فارغة مع رقم حقيقي.
    'test_recipients' => env('WHATSAPP_TEST_RECIPIENTS', ''),

    'default_country_code' => (string) env('WHATSAPP_DEFAULT_COUNTRY_CODE', '972'),
    'country_codes' => ['972', '970'],

];
