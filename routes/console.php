<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// See BACKEND_PROMPT.md section 11 — App\Console\Commands\ExpireLicenses.
Schedule::command('licenses:expire-overdue')->daily();

// See BACKEND_PROMPT.md sections 6/11 — App\Console\Commands\RunScheduledBackup.
Schedule::command('backups:scheduled')->dailyAt('02:00');
