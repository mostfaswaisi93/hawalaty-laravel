<?php

namespace App\Enums;

enum AuditEntityType: string
{
    case Expense = 'expense';
    case Bank = 'bank';
    case MenuItem = 'menuItem';
    case Constant = 'constant';
    case Device = 'device';
    case Order = 'order';
    case TeamMember = 'teamMember';
    case Role = 'role';
}
