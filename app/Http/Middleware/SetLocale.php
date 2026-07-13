<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the user's chosen UI language for the whole Blade web app. The
 * choice is stored in the session by the `locale.switch` route; Arabic is
 * the default (the app is Arabic-first, RTL). Only 'ar' and 'en' are
 * allowed. The layouts read app()->getLocale() to set <html lang/dir> and
 * the font class, and every view resolves its strings through __() against
 * lang/ar.json + lang/en.json.
 */
class SetLocale
{
    public const SUPPORTED = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', 'ar');

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'ar';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
