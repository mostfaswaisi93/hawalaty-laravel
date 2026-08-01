<?php

namespace App\Enums;

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

    public function label(): string
    {
        return match ($this) {
            self::Transfers => __('الطلبات والحوالات'),

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
