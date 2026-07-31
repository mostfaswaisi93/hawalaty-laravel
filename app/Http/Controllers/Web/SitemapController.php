<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\Response;

/**
 * /sitemap.xml for the public marketing pages.
 *
 * Generated rather than a static file in /public: the URLs come from the route
 * table, so a renamed route can never leave a 404 advertised to every crawler.
 *
 * Each entry carries `xhtml:link` alternates for both locales. The site serves
 * one URL per page and switches language from the session, so without those a
 * crawler has no way to learn the Arabic and English versions are the same
 * page - and Google treats the pair as thin duplicate content.
 */
class SitemapController extends Controller
{
    /**
     * Route name => [change frequency, priority].
     *
     * The landing page is the only one that changes with the product; the legal
     * and trust pages change when the policy does, which is rarely.
     */
    private const PAGES = [
        'home' => ['weekly', '1.0'],
        'security' => ['monthly', '0.7'],
        'help.index' => ['monthly', '0.6'],
        'privacy' => ['yearly', '0.4'],
        'terms' => ['yearly', '0.4'],
        'login' => ['yearly', '0.3'],
        'signup' => ['monthly', '0.6'],
    ];

    public function __invoke(): Response
    {
        $lastModified = now()->toAtomString();

        $urls = collect(self::PAGES)
            ->map(fn (array $meta, string $name) => $this->url(route($name), $meta[0], $meta[1], $lastModified))
            ->implode('');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'
            .$urls
            .'</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function url(string $loc, string $frequency, string $priority, string $lastModified): string
    {
        $alternates = collect(SetLocale::SUPPORTED)
            ->map(fn (string $locale) => sprintf(
                '<xhtml:link rel="alternate" hreflang="%s" href="%s"/>',
                $locale,
                e($loc),
            ))
            ->implode('');

        return '<url>'
            .'<loc>'.e($loc).'</loc>'
            .'<lastmod>'.$lastModified.'</lastmod>'
            .'<changefreq>'.$frequency.'</changefreq>'
            .'<priority>'.$priority.'</priority>'
            .$alternates
            .'<xhtml:link rel="alternate" hreflang="x-default" href="'.e($loc).'"/>'
            .'</url>';
    }
}
