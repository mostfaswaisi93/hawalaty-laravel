<?php

namespace App\Enums;

enum PermissionKey: string
{
    case TransfersCreate = 'transfers.create';
    case MenuManage = 'menu.manage';
    case BanksManage = 'banks.manage';
    case ExpensesManage = 'expenses.manage';
    case ConstantsManage = 'constants.manage';
    case DevicesManage = 'devices.manage';
    case TeamManage = 'team.manage';
    case InventoryManage = 'inventory.manage';
    case PurchasesManage = 'purchases.manage';

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
            self::InventoryManage => __('إدارة المخزون'),
            self::PurchasesManage => __('إدارة المشتريات'),
        };
    }

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
            self::InventoryManage => __('تسوية الأرصدة وتسجيل التلف والنقل والجرد.'),
            self::PurchasesManage => __('إدارة الموردين وأوامر الشراء واستلام البضاعة.'),
        };
    }

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
            self::InventoryManage => 'inventory_2',
            self::PurchasesManage => 'local_shipping',
        };
    }
}
