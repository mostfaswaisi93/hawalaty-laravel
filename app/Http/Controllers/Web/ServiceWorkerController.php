<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

/**
 * Serves /sw.js with the current build stamped into its cache name.
 *
 * The worker used a fixed `hawalaty-v1`, so its cache outlived every deploy: it
 * kept a page it had cached along with the hashed assets that page referenced,
 * and Vite deletes those files on the next build. A browser that fell back to
 * the cached page then asked for an asset that no longer exists - served stale
 * from cache, or not at all, leaving a page with no JavaScript running on it.
 *
 * Stamping the build id changes the FILE, which is what makes a browser install
 * the new worker at all, and changes the CACHE NAME, so the activate handler
 * drops everything the previous build left behind. The response must not be
 * cached itself, or the update is delayed by exactly as long as the HTTP cache
 * holds it.
 */
class ServiceWorkerController extends Controller
{
    public function __invoke(): Response
    {
        $source = resource_path('sw.js');

        abort_unless(File::exists($source), 404);

        $script = str_replace('__BUILD_ID__', $this->buildId(), File::get($source));

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache, must-revalidate',
            // A worker registered at / must be allowed to control /.
            'Service-Worker-Allowed' => '/',
        ]);
    }

    /**
     * Identifies the asset build. The manifest names every hashed file, so its
     * hash changes whenever any of them does - and never when nothing has.
     *
     * Falls back to the app version so a missing manifest yields a stable name
     * rather than a new cache on every request.
     */
    private function buildId(): string
    {
        $manifest = public_path('build/manifest.json');

        if (! File::exists($manifest)) {
            return 'dev-'.substr(md5((string) config('app.version', 'v1')), 0, 12);
        }

        return substr(md5_file($manifest), 0, 12);
    }
}
