<?php

namespace App\Http\Controllers\Web\Concerns;

use Illuminate\Http\Request;

trait HasPerPage
{
    protected function perPage(Request $request, int $default = 10): int
    {
        $perPage = (int) $request->query('per_page', $default);

        return in_array($perPage, [10, 50, 100], true) ? $perPage : $default;
    }
}
