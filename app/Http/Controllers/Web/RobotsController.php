<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * robots.txt.
 *
 * Served from a route rather than /public so the `Sitemap:` directive can be an
 * absolute URL built from the current host - the spec requires absolute, and a
 * static file would have to hardcode one domain and be wrong everywhere else.
 */
class RobotsController extends Controller
{
    /**
     * Paths a crawler has no business in. Everything here is behind auth or is
     * the platform operator's own portal; listing them keeps them out of the
     * crawl budget rather than relying on the redirect to /login.
     */
    private const DISALLOWED = [
        '/admin', '/login', '/signup', '/logout', '/sync',
        '/dashboard', '/transfers', '/history', '/accounts', '/customers',
        '/catalogs', '/menu', '/expenses', '/balance', '/reports', '/receipts',
        '/settings', '/team', '/roles', '/devices', '/constants',
        '/notifications', '/activity-log', '/whatsapp',
    ];

    public function __invoke(): Response
    {
        $lines = ['User-agent: *', 'Allow: /'];

        foreach (self::DISALLOWED as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap');

        return response(implode(PHP_EOL, $lines).PHP_EOL, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
