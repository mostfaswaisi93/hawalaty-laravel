<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
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
