<?php

namespace App\Enums;

/**
 * The feature keys a package may grant, stored inside `packages.features`
 * (a JSON array of strings).
 *
 * That column predates this enum and accepts free text: the admin form is a
 * newline textarea, so an operator can type anything. This enum does NOT
 * restrict it - it names the subset the application actually gates on, so a
 * capability like WhatsApp can be sold per package instead of being on for
 * everyone.
 *
 * A key absent from this enum is still stored and still shown on the package
 * card; it simply gates nothing. That keeps every existing package row valid
 * and lets marketing bullet points live in the same list.
 */
enum PackageFeature: string
{
    case Transfers = 'transfers';
    case Menu = 'menu';
    case Reports = 'reports';
    case Banks = 'banks';
    case Expenses = 'expenses';
    case Team = 'team';
    case Devices = 'devices';
    case Constants = 'constants';
    case Backups = 'backups';
    case WhatsApp = 'whatsapp';

    /** What the operator sees when ticking it on a package. */
    public function label(): string
    {
        return match ($this) {
            self::Transfers => __('الطلبات والحوالات'),
            // Platform-side wording, so it cannot use the tenant's own label -
            // one package is sold to a bakery and a fuel station alike. Kept
            // trade-neutral for the same reason CatalogNaming's default is.
            self::Menu => __('قائمة الأصناف'),
            self::Reports => __('التقارير'),
            self::Banks => __('البنوك والمحافظ'),
            self::Expenses => __('المصروفات'),
            self::Team => __('الفريق والصلاحيات'),
            self::Devices => __('إدارة الأجهزة'),
            self::Constants => __('الثوابت'),
            self::Backups => __('النسخ الاحتياطي'),
            self::WhatsApp => __('واتساب API'),
        };
    }

    /** One line on what including it actually unlocks for the tenant. */
    public function description(): string
    {
        return match ($this) {
            self::Transfers => __('إنشاء الطلبات وتسجيل مدفوعاتها.'),
            self::Menu => __('إدارة الفئات والأصناف والأسعار.'),
            self::Reports => __('تقارير الإيرادات والمصروفات.'),
            self::Banks => __('إضافة وسائل الدفع وتفعيلها.'),
            self::Expenses => __('تسجيل المصروفات وصرف الرواتب.'),
            self::Team => __('دعوة الموظفين وضبط صلاحياتهم.'),
            self::Devices => __('ربط الأجهزة وإدارتها.'),
            self::Constants => __('ضبط الثوابت والإعدادات العامة.'),
            self::Backups => __('نسخ احتياطي لبيانات المستأجر.'),
            self::WhatsApp => __('ربط حساب واتساب الأعمال وإرسال إشعارات الطلبات تلقائيًا.'),
        };
    }
}
