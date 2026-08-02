<?php

namespace App\Enums;

/**
 * The twelve business verticals a license can be provisioned for.
 *
 * Fixed platform-side data: an operator picks one when creating the license, and
 * that choice decides which capabilities - and therefore which screens, fields and
 * rules - exist inside the tenant workspace. Tenants cannot change it.
 *
 * @see BusinessCapability
 */
enum BusinessType: string
{
    case Restaurant = 'restaurant';
    case Bakery = 'bakery';
    case Fuel = 'fuel';
    case Produce = 'produce';
    case Grocery = 'grocery';
    case Bookstore = 'bookstore';
    case Tobacco = 'tobacco';
    case Clothing = 'clothing';
    case Electronics = 'electronics';
    case Pharmacy = 'pharmacy';
    case AutoParts = 'auto_parts';
    case Services = 'services';

    public static function default(): self
    {
        return self::Grocery;
    }

    public function label(): string
    {
        return match ($this) {
            self::Restaurant => __('المطاعم والمقاهي'),
            self::Bakery => __('المخبوزات'),
            self::Fuel => __('المحروقات'),
            self::Produce => __('الخضار والفواكه'),
            self::Grocery => __('البقالة والسوبرماركت'),
            self::Bookstore => __('الكتب والقرطاسية'),
            self::Tobacco => __('السجائر والتبغ'),
            self::Clothing => __('الملابس'),
            self::Electronics => __('الإلكترونيات'),
            self::Pharmacy => __('الصيدليات'),
            self::AutoParts => __('قطع الغيار'),
            self::Services => __('الخدمات'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Restaurant => 'restaurant_menu',
            self::Bakery => 'bakery_dining',
            self::Fuel => 'local_gas_station',
            self::Produce => 'nutrition',
            self::Grocery => 'storefront',
            self::Bookstore => 'menu_book',
            self::Tobacco => 'smoking_rooms',
            self::Clothing => 'checkroom',
            self::Electronics => 'smartphone',
            self::Pharmacy => 'pill',
            self::AutoParts => 'build',
            self::Services => 'home_repair_service',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Restaurant => __('أطباق ووجبات بإضافات وخيارات، وخصم المكوّنات من المخزون.'),
            self::Bakery => __('إنتاج يومي بالوزن والحبة، مع تتبّع الفاقد والصلاحية القصيرة.'),
            self::Fuel => __('بيع بالحجم عبر مضخات وخزانات، مع تسوية المخزون الرطب.'),
            self::Produce => __('بيع بالوزن بأسعار متغيّرة، مع أكواد PLU وإدارة التلف.'),
            self::Grocery => __('تشكيلة واسعة بالباركود، مع الصلاحية والعروض وإعادة الطلب.'),
            self::Bookstore => __('كتب بمعرّف ISBN وبيانات النشر، وقرطاسية عادية.'),
            self::Tobacco => __('منتجات مقيّدة بالسن مع الطوابع الضريبية والتتبّع.'),
            self::Clothing => __('مصفوفة مقاس × لون، مواسم ومجموعات، واسترجاع واستبدال.'),
            self::Electronics => __('أجهزة بأرقام تسلسلية وضمان وأوامر صيانة.'),
            self::Pharmacy => __('أدوية بتتبّع اللوط والصلاحية والوصفات والمواد المراقَبة.'),
            self::AutoParts => __('قطع مرتبطة بمركبات محدّدة، بمراجع وكالة وبدائل وضمان.'),
            self::Services => __('خدمات بالوقت عبر مواعيد وفنيين وأوامر شغل.'),
        };
    }

    /**
     * Default catalog terminology seeded into the tenant workspace.
     *
     * @return array{catalog: string, category: string, item: string}
     */
    public function naming(): array
    {
        return match ($this) {
            self::Restaurant => ['catalog' => __('قائمة الطعام'), 'category' => __('قسم'), 'item' => __('طبق')],
            self::Bakery => ['catalog' => __('المخبوزات'), 'category' => __('نوع'), 'item' => __('منتج')],
            self::Fuel => ['catalog' => __('المحروقات'), 'category' => __('نوع'), 'item' => __('صنف وقود')],
            self::Produce => ['catalog' => __('الخضار والفواكه'), 'category' => __('صنف'), 'item' => __('منتج')],
            self::Grocery => ['catalog' => __('البقالة'), 'category' => __('فئة'), 'item' => __('صنف')],
            self::Bookstore => ['catalog' => __('الكتب والقرطاسية'), 'category' => __('قسم'), 'item' => __('عنوان')],
            self::Tobacco => ['catalog' => __('السجائر والتبغ'), 'category' => __('فئة'), 'item' => __('صنف')],
            self::Clothing => ['catalog' => __('الملابس'), 'category' => __('قسم'), 'item' => __('موديل')],
            self::Electronics => ['catalog' => __('الإلكترونيات'), 'category' => __('فئة'), 'item' => __('جهاز')],
            self::Pharmacy => ['catalog' => __('الأدوية'), 'category' => __('فئة'), 'item' => __('دواء')],
            self::AutoParts => ['catalog' => __('قطع الغيار'), 'category' => __('مجموعة'), 'item' => __('قطعة')],
            self::Services => ['catalog' => __('الخدمات'), 'category' => __('فئة'), 'item' => __('خدمة')],
        };
    }

    /**
     * Everything this vertical switches on. Order is presentation order.
     *
     * @return list<BusinessCapability>
     */
    public function capabilities(): array
    {
        $c = BusinessCapability::class;

        return match ($this) {
            self::Restaurant => [
                $c::Inventory, $c::Purchasing, $c::Recipes, $c::Modifiers,
                $c::Waste, $c::Allergens,
            ],
            self::Bakery => [
                $c::Inventory, $c::Purchasing, $c::Recipes, $c::Batches,
                $c::WeighedItems, $c::Waste, $c::Allergens,
            ],
            self::Fuel => [
                $c::Inventory, $c::Purchasing, $c::WeighedItems, $c::FuelStation,
            ],
            self::Produce => [
                $c::Inventory, $c::Purchasing, $c::Batches, $c::WeighedItems, $c::Waste,
            ],
            self::Grocery => [
                $c::Inventory, $c::Purchasing, $c::Batches, $c::WeighedItems, $c::Waste,
            ],
            self::Bookstore => [
                $c::Inventory, $c::Purchasing,
            ],
            self::Tobacco => [
                $c::Inventory, $c::Purchasing, $c::Batches, $c::AgeRestriction,
            ],
            self::Clothing => [
                $c::Inventory, $c::Purchasing, $c::Variants,
            ],
            self::Electronics => [
                $c::Inventory, $c::Purchasing, $c::Serials, $c::Variants, $c::Warranty,
            ],
            self::Pharmacy => [
                $c::Inventory, $c::Purchasing, $c::Batches, $c::Serials,
                $c::Prescription, $c::Waste,
            ],
            self::AutoParts => [
                $c::Inventory, $c::Purchasing, $c::Serials, $c::Fitment,
                $c::Warranty, $c::Scheduling,
            ],
            self::Services => [
                $c::Scheduling, $c::Warranty,
            ],
        };
    }

    public function hasCapability(BusinessCapability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    /**
     * Whether returned goods may go back on the shelf.
     *
     * Not a policy the shop sets - a property of what it sells. Once food or
     * medicine has left the premises its chain of custody is broken, and no
     * amount of "it looks fine" makes it saleable again. The four verticals
     * below therefore destroy returns rather than restocking them, and the
     * module does not offer the choice.
     */
    public function allowsRestockOnReturn(): bool
    {
        return ! in_array($this, [
            self::Restaurant,
            self::Bakery,
            self::Produce,
            self::Pharmacy,
        ], true);
    }

    /**
     * Days a customer has to bring something back, as a starting policy.
     *
     * Zero means the trade does not take returns at all, which is the honest
     * default for prepared food. Everything here is a default the shop can
     * override - unlike the restock rule above, which it cannot.
     */
    public function returnWindowDays(): int
    {
        return match ($this) {
            self::Restaurant, self::Bakery => 0,
            self::Produce => 1,
            self::Pharmacy => 0,
            self::Electronics => 7,
            self::Clothing => 14,
            self::AutoParts => 14,
            self::Services => 0,
            default => 7,
        };
    }

    /**
     * Unit of measure the catalog defaults to, as a UN/CEFACT Rec 20 code.
     */
    public function defaultUnitCode(): string
    {
        return match ($this) {
            self::Fuel => 'LTR',
            self::Produce => 'KGM',
            self::Services => 'HUR',
            default => 'H87',
        };
    }

    /**
     * Constant lists seeded for this vertical: key => title.
     *
     * These populate the existing constant_lists tables so each workspace opens
     * with the vocabulary its trade actually uses.
     *
     * @return array<string, string>
     */
    public function constantLists(): array
    {
        return match ($this) {
            self::Restaurant => [
                'order_types' => __('أنواع الطلب'),
                'prep_stations' => __('محطات التحضير'),
                'tables' => __('الطاولات'),
                'spice_levels' => __('مستويات الحرارة'),
            ],
            self::Bakery => [
                'waste_reasons' => __('أسباب الفاقد'),
                'production_shifts' => __('ورديات الإنتاج'),
                'fillings' => __('الحشوات'),
            ],
            self::Fuel => [
                'fuel_grades' => __('أنواع الوقود'),
                'shifts' => __('الورديات'),
                'haulers' => __('شركات النقل'),
            ],
            self::Produce => [
                'grades' => __('الدرجات'),
                'origins' => __('المصادر والمزارع'),
                'spoilage_reasons' => __('أسباب التلف'),
                'packaging' => __('أنواع العبوات'),
            ],
            self::Grocery => [
                'brands' => __('العلامات التجارية'),
                'aisles' => __('الأرفف والممرات'),
                'promo_types' => __('أنواع العروض'),
                'origins' => __('بلد المنشأ'),
            ],
            self::Bookstore => [
                'publishers' => __('دور النشر'),
                'bindings' => __('أنواع التجليد'),
                'languages' => __('اللغات'),
                'subjects' => __('التصنيفات الموضوعية'),
            ],
            self::Tobacco => [
                'brands' => __('العلامات التجارية'),
                'tobacco_types' => __('أنواع التبغ'),
                'stamp_classes' => __('فئات الطوابع'),
                'refusal_reasons' => __('أسباب رفض البيع'),
            ],
            self::Clothing => [
                'sizes' => __('المقاسات'),
                'colors' => __('الألوان'),
                'materials' => __('المواد والأنسجة'),
                'seasons' => __('المواسم'),
                'brands' => __('الماركات'),
            ],
            self::Electronics => [
                'brands' => __('الماركات'),
                'warranty_terms' => __('مدد الضمان'),
                'warranty_providers' => __('جهات الضمان'),
                'repair_statuses' => __('حالات الصيانة'),
            ],
            self::Pharmacy => [
                'dosage_forms' => __('الأشكال الصيدلانية'),
                'routes' => __('طرق الإعطاء'),
                'manufacturers' => __('الشركات المصنّعة'),
                'control_schedules' => __('جداول الرقابة'),
                'prescribers' => __('الأطباء'),
            ],
            self::AutoParts => [
                'vehicle_makes' => __('ماركات المركبات'),
                'part_groups' => __('مجموعات القطع'),
                'part_conditions' => __('حالة القطعة'),
                'warranty_terms' => __('مدد الضمان'),
            ],
            self::Services => [
                'service_types' => __('أنواع الخدمات'),
                'technicians' => __('الفنيون'),
                'work_order_statuses' => __('حالات أمر الشغل'),
                'cancellation_reasons' => __('أسباب الإلغاء'),
            ],
        };
    }

    /**
     * Headline standards this vertical is designed against.
     *
     * @return list<string>
     */
    public function standards(): array
    {
        return match ($this) {
            self::Restaurant => ['HACCP / Codex', 'ISO 22000', 'EU 1169/2011'],
            self::Bakery => ['HACCP / Codex', 'OIML R76', 'OIML R87'],
            self::Fuel => ['OIML R117', 'OIML R118', 'ASTM D1250', 'UN 1203 / ADR'],
            self::Produce => ['IFPS PLU', 'FSMA 204', 'UNECE marketing standards'],
            self::Grocery => ['GS1 GTIN-13', 'GS1 GPC', 'EU 98/6/EC'],
            self::Bookstore => ['ISBN-13 / ISO 2108', 'ONIX 3.0', 'Thema'],
            self::Tobacco => ['EU TPD Art. 15-16', 'WHO FCTC Protocol'],
            self::Clothing => ['EN 13402', 'ISO 8559', 'ISO 3758', 'EU 1007/2011'],
            self::Electronics => ['IMEI / 3GPP TS 23.003', 'RoHS', 'WEEE', 'CE'],
            self::Pharmacy => ['WHO ATC', 'GS1 DataMatrix', 'EU FMD 2016/161', 'US DSCSA', 'GDP'],
            self::AutoParts => ['TecDoc', 'ACES / PIES', 'ISO 3779 (VIN)', 'UNECE E-mark'],
            self::Services => ['ISO 9001', 'ISO/IEC 20000', 'IFRS 15'],
        };
    }
}
