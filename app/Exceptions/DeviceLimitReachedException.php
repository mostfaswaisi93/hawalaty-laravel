<?php

namespace App\Exceptions;

use RuntimeException;

class DeviceLimitReachedException extends RuntimeException
{
    public function __construct(public readonly int $limit)
    {
        parent::__construct(__('بلغت الحد الأقصى لعدد الأجهزة المسموح بها في باقتك (:limit). ألغِ جهازاً من صفحة الأجهزة ثم أعد المحاولة.', ['limit' => $limit]));
    }
}
