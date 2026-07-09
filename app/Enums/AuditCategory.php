<?php

namespace App\Enums;

enum AuditCategory: string
{
    case Auth = 'auth';
    case Team = 'team';
    case Banks = 'banks';
    case Menu = 'menu';
    case Expenses = 'expenses';
    case Constants = 'constants';
    case Devices = 'devices';
    case Transfers = 'transfers';
}
