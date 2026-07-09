<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Laravel 11's skeleton Controller has no base traits by default — added
 * AuthorizesRequests here since every tenant-space controller in this
 * codebase calls $this->authorize(...) against a Policy before delegating
 * to its Service (see BACKEND_PROMPT.md task instructions).
 */
abstract class Controller
{
    use AuthorizesRequests;
}
