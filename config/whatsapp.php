<?php

return [

    'enabled' => env('WHATSAPP_ENABLED', true),

    'driver' => env('WHATSAPP_DRIVER', 'log'),

    'token' => env('WHATSAPP_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com'),

    'waba_id' => env('WHATSAPP_WABA_ID'),
    'app_secret' => env('WHATSAPP_APP_SECRET'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),

    'template' => [
        'new_order' => env('WHATSAPP_TEMPLATE_NEW_ORDER', 'new_order'),
        'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'ar'),

        'variables' => (int) env('WHATSAPP_TEMPLATE_VARIABLES', 8),
    ],

    'connect_timeout' => (int) env('WHATSAPP_CONNECT_TIMEOUT', 3),
    'timeout' => (int) env('WHATSAPP_TIMEOUT', 5),

    'dedupe_ttl' => (int) env('WHATSAPP_DEDUPE_TTL', 3600),

    'test_recipients' => env('WHATSAPP_TEST_RECIPIENTS', ''),

    'default_country_code' => (string) env('WHATSAPP_DEFAULT_COUNTRY_CODE', '972'),
    'country_codes' => ['972', '970'],

];
