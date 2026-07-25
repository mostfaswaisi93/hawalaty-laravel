<?php

return [

    /*
    |--------------------------------------------------------------------------
    | mysqldump binary
    |--------------------------------------------------------------------------
    |
    | Used by App\Services\Admin\BackupService to shell out to a real
    | mysqldump process (replacing the abandoned spatie/laravel-backup
    | dependency - see BACKEND_PROMPT.md's note on this). Default matches
    | this project's Laragon install; override via MYSQLDUMP_BINARY in .env
    | on any other machine.
    |
    */
    'mysqldump_binary' => env('MYSQLDUMP_BINARY', 'D:/Laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysqldump.exe'),

];
