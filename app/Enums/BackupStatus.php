<?php

namespace App\Enums;

enum BackupStatus: string
{
    case Completed = 'completed';
    case Failed = 'failed';
    case InProgress = 'inProgress';
}
