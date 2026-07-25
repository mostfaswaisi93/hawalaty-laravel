<?php

namespace App\Enums;

/**
 * The 7 fixed permission keys seeded into every tenant database's
 * `permissions` table at provisioning time - see BACKEND_PROMPT.md section 4
 * and D:\Angular\Hawalaty\src\app\data\roles.ts (PermissionKey union type).
 */
enum PermissionKey: string
{
    case TransfersCreate = 'transfers.create';
    case MenuManage = 'menu.manage';
    case BanksManage = 'banks.manage';
    case ExpensesManage = 'expenses.manage';
    case ConstantsManage = 'constants.manage';
    case DevicesManage = 'devices.manage';
    case TeamManage = 'team.manage';
}
