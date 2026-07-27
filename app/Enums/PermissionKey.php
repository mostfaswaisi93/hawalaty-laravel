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

    /**
     * What this permission is called on screen. The raw key is a developer
     * identifier - nobody granting access should have to read "transfers.create"
     * and infer what it lets someone do.
     */
    public function label(): string
    {
        return match ($this) {
            self::TransfersCreate => __('إنشاء الطلبات'),
            self::MenuManage => __('إدارة القائمة'),
            self::BanksManage => __('إدارة البنوك والمحافظ'),
            self::ExpensesManage => __('إدارة المصروفات'),
            self::ConstantsManage => __('إدارة الثوابت'),
            self::DevicesManage => __('إدارة الأجهزة'),
            self::TeamManage => __('إدارة الفريق'),
        };
    }

    /** One line on what granting it actually allows. */
    public function description(): string
    {
        return match ($this) {
            self::TransfersCreate => __('إنشاء طلبات جديدة وتسجيل مدفوعاتها.'),
            self::MenuManage => __('إضافة وتعديل الفئات والأصناف وأسعارها.'),
            self::BanksManage => __('إضافة وتعديل وسائل الدفع وتفعيلها.'),
            self::ExpensesManage => __('تسجيل المصروفات وصرف الرواتب.'),
            self::ConstantsManage => __('تعديل القوائم الثابتة ومسمّيات القائمة.'),
            self::DevicesManage => __('عرض الأجهزة المرتبطة وإلغاء أيّ منها.'),
            self::TeamManage => __('دعوة الأعضاء وتعديل أدوارهم ورواتبهم.'),
        };
    }

    /** Material Symbols glyph, matching each area's own sidebar icon. */
    public function icon(): string
    {
        return match ($this) {
            self::TransfersCreate => 'send',
            self::MenuManage => 'restaurant_menu',
            self::BanksManage => 'account_balance',
            self::ExpensesManage => 'payments',
            self::ConstantsManage => 'tune',
            self::DevicesManage => 'devices',
            self::TeamManage => 'group',
        };
    }
}
