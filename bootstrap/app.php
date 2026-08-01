<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsurePackageFeature;
use App\Http\Middleware\ResolveTenantConnection;
use App\Http\Middleware\ResolveTenantConnectionFromSession;
use App\Http\Middleware\SetLocale;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*')) {
                return null;
            }

            return $request->is('admin/*') ? route('admin.login') : route('login');
        });

        $middleware->redirectUsersTo(
            fn (Request $request) => $request->is('admin/*') ? route('admin.dashboard') : route('dashboard'),
        );

        $middleware->web(append: [SetLocale::class]);

        $middleware->validateCsrfTokens(except: ['webhooks/whatsapp']);

        $middleware->alias([
            'tenant.header' => ResolveTenantConnection::class,

            'tenant.session' => ResolveTenantConnectionFromSession::class,

            'admin' => EnsureAdmin::class,

            'package' => EnsurePackageFeature::class,
        ]);

        $middleware->throttleApi();

        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: ResolveTenantConnection::class,
        );

        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: ResolveTenantConnectionFromSession::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
