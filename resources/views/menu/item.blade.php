@extends('layouts.app')

@section('title', $item->name . ' - ' . __('حوالاتي'))

@php
    use App\Enums\BusinessCapability as Cap;
    use App\Enums\TaxCategory;
    use App\Enums\UnitOfMeasure;

    $field = 'w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none transition-all focus:border-transparent focus:ring-2 focus:ring-primary';
    $label = 'block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant';
    $locked = 'w-full rounded-xl border border-outline-variant bg-surface-container px-4 py-3 text-body-lg text-on-surface-variant';

    $stocked = $business->has(Cap::Inventory);
@endphp

@section('content')
    <div class="page-enter mx-auto max-w-4xl space-y-6" x-data="{ submitting: false, icon: @js(old('icon', $item->icon ?? '')) }">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div class="min-w-0">
                <a href="{{ route('menu.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                    <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                    {{ $naming->catalog() }}
                </a>
                <h1 class="mt-1 font-display-lg text-display-lg text-on-surface">{{ $item->name }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">
                    {{ $item->menuCategory?->name }}
                    @if ($item->isVariant())
                        — {{ __('تنويعة من :parent', ['parent' => $item->parentItem?->name]) }}
                    @endif
                </p>
            </div>

            @if ($stocked && $item->isStocked())
                <a href="{{ route('inventory.show', $item->id) }}"
                    class="flex items-center gap-2 rounded-xl bg-surface-container px-4 py-2.5 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                    <span class="material-symbols-outlined !text-xl">inventory_2</span>
                    {{ __('الرصيد') }}: <span class="font-numeral-lg" dir="ltr">{{ rtrim(rtrim(number_format($onHand, 3), '0'), '.') }}</span>
                </a>
            @endif
        </header>

        <form method="POST" action="{{ route('menu.items.update', $item->id) }}" class="space-y-6" @submit="submitting = true">
            @csrf
            @method('PUT')
            <input type="hidden" name="fromPage" value="1" />

            {{-- Basics ------------------------------------------------------ --}}
            <x-card title="{{ __('الأساسيات') }}">
                <div class="space-y-5">
                    <div class="space-y-1.5">
                        <label for="name" class="{{ $label }}">{{ __('الاسم') }}</label>
                        <input id="name" name="name" value="{{ old('name', $item->name) }}" required maxlength="255" class="{{ $field }}" />
                        @error('name')<p class="text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label for="menuCategoryId" class="{{ $label }}">{{ $naming->category() }}</label>
                            <select id="menuCategoryId" name="menuCategoryId" class="{{ $field }}">
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected(old('menuCategoryId', $item->menu_category_id) === $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label for="price" class="{{ $label }}">{{ __('السعر') }}</label>
                            <input id="price" type="number" step="0.001" min="0" name="price" value="{{ old('price', (float) $item->price) }}" required dir="ltr" class="{{ $field }}" />
                            @error('price')<p class="text-body-sm text-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label for="kind" class="{{ $label }}">{{ __('النوع') }}</label>
                        @if ($hasHistory && ! $item->isService())
                            <p class="{{ $locked }}">{{ __('سلعة') }}</p>
                            <p class="font-body-sm text-outline">{{ __('تحرّك مخزون هذا الصنف، فما عاد يمكن تحويله إلى خدمة.') }}</p>
                        @else
                            <select id="kind" name="kind" class="{{ $field }}">
                                <option value="goods" @selected(old('kind', $item->kind) === 'goods')>{{ __('سلعة') }}</option>
                                <option value="service" @selected(old('kind', $item->kind) === 'service')>{{ __('خدمة') }}</option>
                            </select>
                        @endif
                    </div>

                    <x-icon-picker model="icon" />

                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3">
                        <span class="font-body-sm font-bold text-on-surface">{{ __('متاح للطلب') }}</span>
                        <input type="hidden" name="active" value="0" />
                        <input type="checkbox" name="active" value="1" @checked(old('active', $item->active)) class="h-5 w-5 rounded accent-primary" />
                    </label>
                </div>
            </x-card>

            {{-- Identification ---------------------------------------------- --}}
            <x-card title="{{ __('التعريف والترميز') }}">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="space-y-1.5">
                        <label for="barcode" class="{{ $label }}">{{ __('الباركود') }}</label>
                        <input id="barcode" name="barcode" value="{{ old('barcode', $item->barcode) }}" dir="ltr" maxlength="64" class="{{ $field }}" />
                        @error('barcode')<p class="text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-1.5">
                        <label for="sku" class="{{ $label }}">{{ __('رمز الصنف') }}</label>
                        <input id="sku" name="sku" value="{{ old('sku', $item->sku) }}" dir="ltr" maxlength="64" class="{{ $field }}" />
                        @error('sku')<p class="text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-1.5">
                        <label for="pluCode" class="{{ $label }}">{{ __('رمز PLU') }}</label>
                        <input id="pluCode" name="pluCode" value="{{ old('pluCode', $item->plu_code) }}" dir="ltr" maxlength="16" class="{{ $field }}" />
                    </div>
                </div>
                <p class="mt-3 font-body-sm text-outline">{{ __('الباركود ورمز الصنف لا يتكرّران: رقم يدلّ على صنفين لا يدلّ على أيّهما، والصندوق سيختار عشوائيًا.') }}</p>
            </x-card>

            {{-- Units ------------------------------------------------------- --}}
            @if ($stocked)
                <x-card title="{{ __('وحدة القياس') }}">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="space-y-1.5">
                            <label for="unitCode" class="{{ $label }}">{{ __('وحدة البيع') }}</label>
                            @if ($hasHistory)
                                <p class="{{ $locked }}">{{ $item->unit()->label() }}</p>
                                <p class="font-body-sm text-outline">{{ __('كميات الدفتر مسجّلة بهذه الوحدة.') }}</p>
                            @else
                                <select id="unitCode" name="unitCode" class="{{ $field }}">
                                    @foreach (UnitOfMeasure::cases() as $unit)
                                        <option value="{{ $unit->value }}" @selected(old('unitCode', $item->unit_code) === $unit->value)>{{ $unit->label() }} ({{ $unit->value }})</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        <div class="space-y-1.5">
                            <label for="purchaseUnitCode" class="{{ $label }}">{{ __('وحدة الشراء') }}</label>
                            <select id="purchaseUnitCode" name="purchaseUnitCode" class="{{ $field }}">
                                <option value="">{{ __('نفس وحدة البيع') }}</option>
                                @foreach (UnitOfMeasure::cases() as $unit)
                                    <option value="{{ $unit->value }}" @selected(old('purchaseUnitCode', $item->purchase_unit_code) === $unit->value)>{{ $unit->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label for="purchaseConversion" class="{{ $label }}">{{ __('معامل التحويل') }}</label>
                            <input id="purchaseConversion" type="number" step="0.001" min="0.001" name="purchaseConversion"
                                value="{{ old('purchaseConversion', (float) $item->purchase_conversion) }}" dir="ltr" class="{{ $field }}" />
                            @error('purchaseConversion')<p class="text-body-sm text-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <p class="mt-3 font-body-sm text-outline">{{ __('كم وحدة بيع في وحدة الشراء الواحدة — كرتونة فيها ٢٤ علبة معاملها ٢٤. رمز الوحدة معياري (UN/CEFACT Rec 20) لأن الفاتورة تحمله.') }}</p>
                </x-card>
            @endif

            {{-- Tax ---------------------------------------------------------- --}}
            <x-card title="{{ __('الضريبة') }}">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label for="taxRate" class="{{ $label }}">{{ __('النسبة %') }}</label>
                        <input id="taxRate" type="number" step="0.01" min="0" max="100" name="taxRate" value="{{ old('taxRate', (float) $item->tax_rate) }}" dir="ltr" class="{{ $field }}" />
                        @error('taxRate')<p class="text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-1.5">
                        <label for="taxCategory" class="{{ $label }}">{{ __('الفئة الضريبية') }}</label>
                        <select id="taxCategory" name="taxCategory" class="{{ $field }}">
                            @foreach (TaxCategory::cases() as $case)
                                <option value="{{ $case->value }}" @selected(old('taxCategory', $item->tax_category) === $case->value)>{{ $case->label() }} ({{ $case->value }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <label class="mt-4 flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3">
                    <span class="min-w-0">
                        <span class="block font-body-sm font-bold text-on-surface">{{ __('السعر شامل الضريبة') }}</span>
                        <span class="block font-body-sm text-outline">{{ __('كما هو معروض على الرف') }}</span>
                    </span>
                    <input type="hidden" name="taxIncluded" value="0" />
                    <input type="checkbox" name="taxIncluded" value="1" @checked(old('taxIncluded', $item->tax_included)) class="h-5 w-5 flex-shrink-0 rounded accent-primary" />
                </label>

                <p class="mt-3 font-body-sm text-outline">{{ __('النسبة وحدها لا تفرّق الصفري عن المعفى — كلاهما صفر، والفاتورة يجب أن تقول أيّهما.') }}</p>
            </x-card>

            {{-- Stock -------------------------------------------------------- --}}
            @if ($stocked)
                <x-card title="{{ __('المخزون') }}">
                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3">
                        <span class="min-w-0">
                            <span class="block font-body-sm font-bold text-on-surface">{{ __('تتبّع المخزون') }}</span>
                            <span class="block font-body-sm text-outline">{{ __('يدخل الصنف في دفتر الحركات والجرد') }}</span>
                        </span>
                        <input type="hidden" name="trackStock" value="0" />
                        <input type="checkbox" name="trackStock" value="1" @checked(old('trackStock', $item->track_stock)) class="h-5 w-5 flex-shrink-0 rounded accent-primary" />
                    </label>

                    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label for="cost" class="{{ $label }}">{{ __('التكلفة') }}</label>
                            @if ($hasHistory)
                                <p class="{{ $locked }}" dir="ltr">{{ number_format((float) $item->cost, 3) }}</p>
                                <p class="font-body-sm text-outline">
                                    {{ __('المتوسط المرجّح، يتدحرج مع كل استلام. تعديله يدويًا هنا يجعل الكتالوج يناقض الدفتر — سجّل استلامًا أو تسوية.') }}
                                </p>
                            @else
                                <input id="cost" type="number" step="0.001" min="0" name="cost" value="{{ old('cost', (float) $item->cost) }}" dir="ltr" class="{{ $field }}" />
                                <p class="font-body-sm text-outline">{{ __('تكلفة افتتاحية — بعد أوّل حركة يتولّاها الدفتر.') }}</p>
                            @endif
                        </div>

                        <div class="space-y-1.5">
                            <label for="reorderPoint" class="{{ $label }}">{{ __('نقطة إعادة الطلب') }}</label>
                            <input id="reorderPoint" type="number" step="0.001" min="0" name="reorderPoint"
                                value="{{ old('reorderPoint', $item->reorder_point === null ? '' : (float) $item->reorder_point) }}" dir="ltr" class="{{ $field }}" />
                            <p class="font-body-sm text-outline">{{ __('اتركها فارغة لتعطيل التنبيه.') }}</p>
                        </div>
                    </div>

                    @if (abs($onHand) > 0.0005)
                        <p class="mt-4 rounded-xl bg-surface-container-low p-3 font-body-sm text-on-surface-variant">
                            {{ __('الرصيد الحالي :qty — لا يمكن إيقاف التتبّع ولا تشغيل التسلسلي قبل تصفيته.', ['qty' => rtrim(rtrim(number_format($onHand, 3), '0'), '.')]) }}
                        </p>
                    @endif
                </x-card>
            @endif

            {{-- Serials and warranty ----------------------------------------- --}}
            @if ($business->hasAny([Cap::Serials, Cap::Warranty]))
                <x-card title="{{ __('التتبّع التسلسلي والضمان') }}">
                    @if ($business->has(Cap::Serials))
                        <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3">
                            <span class="min-w-0">
                                <span class="block font-body-sm font-bold text-on-surface">{{ __('تتبّع تسلسلي') }}</span>
                                <span class="block font-body-sm text-outline">{{ __('كل وحدة برقمها، ولا تخرج مجهولة') }}</span>
                            </span>
                            <input type="hidden" name="trackSerials" value="0" />
                            <input type="checkbox" name="trackSerials" value="1" @checked(old('trackSerials', $item->track_serials)) class="h-5 w-5 flex-shrink-0 rounded accent-primary" />
                        </label>
                    @endif

                    @if ($business->has(Cap::Warranty))
                        <div class="mt-5 space-y-1.5">
                            <label for="warrantyMonths" class="{{ $label }}">{{ __('مدة الضمان بالأشهر') }}</label>
                            <input id="warrantyMonths" type="number" step="1" min="0" max="600" name="warrantyMonths"
                                value="{{ old('warrantyMonths', $item->warranty_months) }}" dir="ltr" class="{{ $field }}" />
                            <p class="font-body-sm text-outline">{{ __('تبدأ من تاريخ البيع لكل وحدة على حدة، لا من تاريخ الاستلام.') }}</p>
                            @error('warrantyMonths')<p class="text-body-sm text-error">{{ $message }}</p>@enderror
                        </div>
                    @endif
                </x-card>
            @endif

            {{-- Service ------------------------------------------------------ --}}
            @if ($business->has(Cap::Scheduling))
                <x-card title="{{ __('الخدمة') }}">
                    <div class="space-y-1.5">
                        <label for="serviceMinutes" class="{{ $label }}">{{ __('المدة بالدقائق') }}</label>
                        <input id="serviceMinutes" type="number" step="1" min="0" max="1440" name="serviceMinutes"
                            value="{{ old('serviceMinutes', $item->service_minutes) }}" dir="ltr" class="{{ $field }}" />
                        <p class="font-body-sm text-outline">{{ __('تُحجز بها المواعيد وتُحسب بها الفجوات المتاحة.') }}</p>
                        @error('serviceMinutes')<p class="text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                </x-card>
            @endif

            {{-- Parts -------------------------------------------------------- --}}
            @if ($business->has(Cap::Fitment))
                <x-card title="{{ __('قطع الغيار') }}">
                    <div class="space-y-1.5">
                        <label for="coreCharge" class="{{ $label }}">{{ __('وديعة القطعة القديمة') }}</label>
                        <input id="coreCharge" type="number" step="0.01" min="0" name="coreCharge"
                            value="{{ old('coreCharge', (float) $item->core_charge) }}" dir="ltr" class="{{ $field }}" />
                        <p class="font-body-sm text-outline">{{ __('مبلغ يُحصَّل ويُردّ عند إعادة القطعة التالفة.') }}</p>
                    </div>

                    <a href="{{ route('fitment.edit', $item->id) }}" class="mt-4 inline-flex items-center gap-1 font-body-sm font-bold text-primary hover:underline">
                        {{ __('الملاءمة والمراجع المتقاطعة') }}
                        <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">chevron_right</span>
                    </a>
                </x-card>
            @endif

            {{-- Pharmacy ----------------------------------------------------- --}}
            @if ($business->has(Cap::Prescription))
                <x-card title="{{ __('بيانات الدواء') }}">
                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3">
                        <span class="min-w-0">
                            <span class="block font-body-sm font-bold text-on-surface">{{ __('يُصرف بوصفة') }}</span>
                            <span class="block font-body-sm text-outline">{{ __('الصرف بلا وصفة يُرفض، ولا يُكتفى بالتنبيه') }}</span>
                        </span>
                        <input type="hidden" name="isPrescription" value="0" />
                        <input type="checkbox" name="isPrescription" value="1" @checked(old('isPrescription', $item->is_prescription)) class="h-5 w-5 flex-shrink-0 rounded accent-primary" />
                    </label>

                    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label for="controlSchedule" class="{{ $label }}">{{ __('الجدول الرقابي') }}</label>
                            <input id="controlSchedule" name="controlSchedule" value="{{ old('controlSchedule', $item->control_schedule) }}"
                                list="schedules" dir="ltr" maxlength="16" class="{{ $field }}" />
                            <datalist id="schedules">
                                <option value="I"></option>
                                <option value="II"></option>
                                <option value="III"></option>
                                <option value="IV"></option>
                                <option value="V"></option>
                            </datalist>
                            <p class="font-body-sm text-outline">{{ __('تسمية الجداول وطنية. أي قيمة هنا تجعل الصنف مراقَبًا وتفرض تسجيل هوية المريض.') }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="atcCode" class="{{ $label }}">{{ __('رمز ATC') }}</label>
                            <input id="atcCode" name="atcCode" value="{{ old('atcCode', $item->atc_code) }}" dir="ltr" maxlength="16" placeholder="N02BE01" class="{{ $field }}" />
                            <p class="font-body-sm text-outline">{{ __('تصنيف منظمة الصحة العالمية — يربط الدواء ببدائله.') }}</p>
                            @error('atcCode')<p class="text-body-sm text-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="activeIngredient" class="{{ $label }}">{{ __('المادة الفعّالة') }}</label>
                            <input id="activeIngredient" name="activeIngredient" value="{{ old('activeIngredient', $item->active_ingredient) }}" maxlength="255" class="{{ $field }}" />
                            <p class="font-body-sm text-outline">{{ __('بالاسم الدولي غير المملوك (INN).') }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="strength" class="{{ $label }}">{{ __('التركيز') }}</label>
                            <input id="strength" name="strength" value="{{ old('strength', $item->strength) }}" dir="ltr" maxlength="64" placeholder="500 mg" class="{{ $field }}" />
                        </div>

                        <div class="space-y-1.5">
                            <label for="dosageForm" class="{{ $label }}">{{ __('الشكل الصيدلاني') }}</label>
                            <input id="dosageForm" name="dosageForm" value="{{ old('dosageForm', $item->dosage_form) }}" maxlength="64" placeholder="{{ __('أقراص') }}" class="{{ $field }}" />
                        </div>
                    </div>

                    <label class="mt-4 flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3">
                        <span class="min-w-0">
                            <span class="block font-body-sm font-bold text-on-surface">{{ __('سلسلة تبريد') }}</span>
                            <span class="block font-body-sm text-outline">{{ __('يفسد بخروجه عن حدود الحرارة') }}</span>
                        </span>
                        <input type="hidden" name="coldChain" value="0" />
                        <input type="checkbox" name="coldChain" value="1" @checked(old('coldChain', $item->cold_chain)) class="h-5 w-5 flex-shrink-0 rounded accent-primary" />
                    </label>
                </x-card>
            @endif

            <div class="flex justify-end gap-3">
                <a href="{{ route('menu.index') }}" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</a>
                <button type="submit" :disabled="submitting"
                    class="flex items-center gap-2 rounded-xl bg-primary px-6 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60">
                    <span class="material-symbols-outlined !text-xl">save</span>
                    <span x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ'))">{{ __('حفظ') }}</span>
                </button>
            </div>
        </form>
    </div>
@endsection
