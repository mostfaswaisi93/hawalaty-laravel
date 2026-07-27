<?php

namespace App\Http\Controllers\Web\Concerns;

use Illuminate\Http\Request;

/**
 * Resolves the `per_page` query parameter for paginated tables, whitelisted to
 * the 10 / 50 / 100 options exposed in the UI so a crafted value can never
 * request an unbounded page size.
 *
 * Lives under Web\Concerns rather than Web\Admin\Concerns because the tenant
 * activity log uses it too.
 */
trait HasPerPage
{
    protected function perPage(Request $request, int $default = 10): int
    {
        $perPage = (int) $request->query('per_page', $default);

        return in_array($perPage, [10, 50, 100], true) ? $perPage : $default;
    }
}
