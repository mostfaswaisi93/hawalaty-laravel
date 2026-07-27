<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The license package's device allowance is full. Carries the limit so the
 * message can name it - "3 devices" is actionable, "limit reached" is not.
 */
class DeviceLimitReachedException extends RuntimeException
{
    public function __construct(public readonly int $limit)
    {
        parent::__construct(__('بلغت الحد الأقصى لعدد الأجهزة المسموح بها في باقتك (:limit). ألغِ جهازاً من صفحة الأجهزة ثم أعد المحاولة.', ['limit' => $limit]));
    }
}
