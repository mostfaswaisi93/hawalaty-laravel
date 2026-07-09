<?php

use App\Models\Admin;
use App\Models\Tenant\TeamMember;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // Super Admin portal — session/cookie login for the Blade web app
        // (resources/views/auth/admin-login.blade.php,
        // App\Http\Controllers\Web\Auth\AdminAuthController). Kept distinct
        // from the default `web` guard (even though both currently share the
        // `users`/Admin provider) so route middleware can say `auth:admin`
        // explicitly rather than relying on Laravel's implicit default guard
        // — see this task's report for the reasoning.
        'admin' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // Tenant space — session/cookie login for a license's team members
        // (App\Http\Controllers\Web\Auth\TenantAuthController). ALWAYS
        // paired with the `tenant.session` middleware ahead of it
        // (`['tenant.session', 'auth:tenant']`) so the `team_members`
        // provider below queries the correct tenant database — see
        // App\Http\Middleware\ResolveTenantConnectionFromSession and
        // BACKEND_PROMPT.md section 8.1.
        'tenant' => [
            'driver' => 'session',
            'provider' => 'team_members',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', Admin::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],

        // Backs the `tenant` guard above. TeamMember::getConnectionName()
        // always returns 'tenant' (see the model), so this provider's
        // queries automatically hit whichever tenant database
        // ResolveTenantConnectionFromSession most recently pointed the
        // dynamic `tenant` connection at — never the platform database.
        'team_members' => [
            'driver' => 'eloquent',
            'model' => TeamMember::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
