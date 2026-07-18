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
    // throwing — a typo in .env must never 500 the order screen.
    'driver' => env('WHATSAPP_DRIVER', 'log'),

    // No defaults for credentials — a secret must never be baked into config.
    'token' => env('WHATSAPP_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com'),
    'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),

    /*
    | Meta rejects free-form text sent outside a 24-hour customer-initiated
    | window (error 131047). A bank's number never initiates a conversation, so
    | the `cloud` driver ALWAYS sends an approved template. Create a template
    | with this name in Meta Business Manager whose body contains exactly ONE
    | positional parameter {{1}} — the whole rendered order text is passed as
    | that parameter. A parameter-count mismatch returns error 132000.
    */
    'template' => [
        'new_order' => env('WHATSAPP_TEMPLATE_NEW_ORDER', 'new_order'),
        'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'ar'),
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
    'default_country_code' => (string) env('WHATSAPP_DEFAULT_COUNTRY_CODE', '972'),
    'country_codes' => ['972', '970'],

];
