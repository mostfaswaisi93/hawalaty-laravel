<?php

namespace App\Enums;

/**
 * The behavioural axes a business vertical can switch on.
 *
 * A vertical is not a label - it is a set of capabilities. Twelve verticals are
 * built out of these axes rather than twelve separate systems, so a capability is
 * implemented once and reused by every vertical that declares it.
 *
 * @see BusinessType::capabilities()
 */
enum BusinessCapability: string
{
    /** Append-only stock ledger, locations, on-hand balances. */
    case Inventory = 'inventory';

    /** Suppliers, purchase orders, goods receipt - the only legitimate stock inflow. */
    case Purchasing = 'purchasing';

    /** Lot/batch tracking with expiry dates and FEFO issuing. */
    case Batches = 'batches';

    /** Per-unit serial numbers (IMEI, chassis, device serial). */
    case Serials = 'serials';

    /** Size x colour style matrix - one product, many stock-keeping variants. */
    case Variants = 'variants';

    /** Bill of materials: selling a finished item depletes its components. */
    case Recipes = 'recipes';

    /** Sale-time options and add-ons (no onion, extra cheese, large). */
    case Modifiers = 'modifiers';

    /** Fractional quantities sold by weight or volume, scale labels, PLU codes. */
    case WeighedItems = 'weighed_items';

    /** Spoilage, breakage and end-of-day write-offs as a first-class movement. */
    case Waste = 'waste';

    /** Warranty terms, claims and RMA flow. */
    case Warranty = 'warranty';

    /** Vehicle compatibility, OE cross-references, superseded parts. */
    case Fitment = 'fitment';

    /** Appointments, resources and work orders - time is the stock. */
    case Scheduling = 'scheduling';

    /** Tanks, pumps, nozzles, meter readings and wetstock reconciliation. */
    case FuelStation = 'fuel_station';

    /** Minimum legal age prompt recorded against the sale. */
    case AgeRestriction = 'age_restriction';

    /** Rx vs OTC, prescriber details, controlled-substance register. */
    case Prescription = 'prescription';

    /** Declarable allergens on every sellable item. */
    case Allergens = 'allergens';

    public function label(): string
    {
        return match ($this) {
            self::Inventory => __('المخزون'),
            self::Purchasing => __('المشتريات والموردون'),
            self::Batches => __('اللوط والصلاحية'),
            self::Serials => __('الأرقام التسلسلية'),
            self::Variants => __('التنويعات'),
            self::Recipes => __('الوصفات والمكوّنات'),
            self::Modifiers => __('الإضافات والخيارات'),
            self::WeighedItems => __('البيع بالوزن والحجم'),
            self::Waste => __('الفاقد والتلف'),
            self::Warranty => __('الضمان والصيانة'),
            self::Fitment => __('التوافق مع المركبات'),
            self::Scheduling => __('المواعيد وأوامر الشغل'),
            self::FuelStation => __('الخزانات والمضخات'),
            self::AgeRestriction => __('التحقق من العمر'),
            self::Prescription => __('الوصفات الطبية'),
            self::Allergens => __('مسبّبات الحساسية'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Inventory => __('دفتر حركات مخزنية، مواقع، أرصدة، وجرد فعلي.'),
            self::Purchasing => __('موردون وأوامر شراء واستلام بضاعة وتكلفة فعلية.'),
            self::Batches => __('تتبّع رقم اللوط وتاريخ الصلاحية وصرف المنتهي أولًا.'),
            self::Serials => __('رقم تسلسلي فريد لكل وحدة يُلتقط عند الشراء والبيع.'),
            self::Variants => __('مصفوفة مقاس × لون بمخزون وباركود مستقل لكل تنويعة.'),
            self::Recipes => __('خصم المكوّنات من المخزون عند بيع الصنف الجاهز.'),
            self::Modifiers => __('خيارات وإضافات تُختار وقت البيع وتؤثّر على السعر.'),
            self::WeighedItems => __('كميات كسرية، أكواد PLU، وباركود الوزن المتغيّر.'),
            self::Waste => __('تسجيل التلف والفاقد كحركة منفصلة عن البيع.'),
            self::Warranty => __('مدد الضمان ومطالباته وأوامر الصيانة والإرجاع.'),
            self::Fitment => __('ربط القطعة بالمركبات المتوافقة وأرقام الوكالة والبدائل.'),
            self::Scheduling => __('حجز المواعيد وتخصيص الفنيين ومتابعة أوامر الشغل.'),
            self::FuelStation => __('قراءات العدادات وتسوية المخزون الرطب وتاريخ الأسعار.'),
            self::AgeRestriction => __('تنبيه إلزامي للتحقق من السن مع تسجيل حدوثه.'),
            self::Prescription => __('تمييز الدواء الموصوف وتسجيل الطبيب والمواد الخاضعة للرقابة.'),
            self::Allergens => __('الإفصاح عن مسبّبات الحساسية الأربعة عشر على كل صنف.'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Inventory => 'inventory_2',
            self::Purchasing => 'local_shipping',
            self::Batches => 'event_available',
            self::Serials => 'pin',
            self::Variants => 'grid_view',
            self::Recipes => 'blender',
            self::Modifiers => 'tune',
            self::WeighedItems => 'scale',
            self::Waste => 'delete_sweep',
            self::Warranty => 'verified_user',
            self::Fitment => 'directions_car',
            self::Scheduling => 'calendar_month',
            self::FuelStation => 'local_gas_station',
            self::AgeRestriction => 'no_adult_content',
            self::Prescription => 'prescriptions',
            self::Allergens => 'allergies',
        };
    }

    /**
     * The international standards this capability exists to satisfy.
     *
     * Surfaced in the admin reference screen so an operator can see why a
     * vertical switches a module on, not just that it does.
     *
     * @return list<string>
     */
    public function standards(): array
    {
        return match ($this) {
            self::Inventory => ['IAS 2', 'UN/CEFACT Rec 20'],
            self::Purchasing => ['IAS 2', 'EN 16931'],
            self::Batches => ['GS1 AI 10/17', 'Codex Alimentarius', 'FSMA 204'],
            self::Serials => ['GS1 AI 21', '3GPP TS 23.003 (IMEI)'],
            self::Variants => ['GS1 GTIN', 'EN 13402', 'ISO 8559'],
            self::Recipes => ['HACCP', 'ISO 22000'],
            self::Modifiers => ['EU 1169/2011'],
            self::WeighedItems => ['OIML R76', 'IFPS PLU', 'GS1 prefixes 20-29'],
            self::Waste => ['HACCP', 'IAS 2'],
            self::Warranty => ['ISO 9001'],
            self::Fitment => ['ACES/PIES', 'TecDoc', 'ISO 3779 (VIN)'],
            self::Scheduling => ['ISO 9001', 'IFRS 15'],
            self::FuelStation => ['OIML R117', 'OIML R118', 'ASTM D1250'],
            self::AgeRestriction => ['WHO FCTC', 'EU TPD'],
            self::Prescription => ['WHO ATC', 'EU FMD 2016/161', 'US DSCSA', 'GDP'],
            self::Allergens => ['EU 1169/2011', 'Codex Alimentarius'],
        };
    }

    /**
     * Capabilities that cannot function without another one being on.
     *
     * @return list<self>
     */
    public function requires(): array
    {
        return match ($this) {
            self::Batches, self::Serials, self::Variants, self::Recipes, self::Waste => [self::Inventory],
            self::Purchasing => [self::Inventory],
            default => [],
        };
    }
}
