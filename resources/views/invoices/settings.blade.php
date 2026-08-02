@extends('layouts.app')

@section('title', __('بيانات البائع - حوالاتي'))

@section('content')
    <div class="page-enter mx-auto max-w-3xl space-y-6">
        <header>
            <a href="{{ route('invoices.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                {{ __('الفوترة الإلكترونية') }}
            </a>
            <h1 class="mt-1 font-display-lg text-display-lg text-on-surface">{{ __('بيانات البائع') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('تُنسَخ هذه البيانات داخل كل فاتورة لحظة إصدارها، فتبقى الفاتورة صحيحة حتى لو تغيّر المحل بعدها.') }}</p>
        </header>

        <form method="POST" action="{{ route('invoices.settings.save') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <x-card title="{{ __('الهوية الضريبية') }}">
                <div class="space-y-5">
                    <x-form-field
                        name="sellerName"
                        label="{{ __('الاسم التجاري') }}"
                        icon="storefront"
                        placeholder="{{ __('كما هو مسجَّل رسميًا') }}"
                        :value="$settings[\App\Services\Tenant\InvoiceService::SELLER_NAME]"
                        required />

                    <x-form-field
                        name="sellerTaxNumber"
                        label="{{ __('الرقم الضريبي') }}"
                        icon="badge"
                        dir="ltr"
                        placeholder="{{ __('اتركه فارغًا إن لم تكن مسجَّلًا') }}"
                        :value="$settings[\App\Services\Tenant\InvoiceService::SELLER_TAX_NUMBER]" />

                    <x-form-field
                        name="sellerAddress"
                        label="{{ __('العنوان') }}"
                        icon="location_on"
                        :value="$settings[\App\Services\Tenant\InvoiceService::SELLER_ADDRESS]" />

                    <div class="space-y-2">
                        <label for="currency" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('العملة') }}</label>
                        <select id="currency" name="currency"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            @foreach (['ILS' => __('شيكل'), 'JOD' => __('دينار أردني'), 'USD' => __('دولار'), 'EUR' => __('يورو'), 'SAR' => __('ريال سعودي'), 'AED' => __('درهم إماراتي'), 'EGP' => __('جنيه مصري')] as $code => $name)
                                <option value="{{ $code }}" @selected(old('currency', $settings[\App\Services\Tenant\InvoiceService::CURRENCY]) === $code)>{{ $name }} ({{ $code }})</option>
                            @endforeach
                        </select>
                        <p class="font-body-sm text-outline">{{ __('رمز ISO 4217. يُثبَّت على كل فاتورة تصدر بعده، وتغييره لاحقًا لا يمسّ ما صدر.') }}</p>
                        @error('currency')
                            <p class="text-body-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </x-card>

            <div class="flex items-start gap-3 rounded-2xl border border-outline-variant bg-surface-container-low p-4">
                <span class="material-symbols-outlined flex-shrink-0 text-on-surface-variant">info</span>
                <div class="min-w-0 font-body-sm text-on-surface-variant">
                    <p class="font-bold text-on-surface">{{ __('ما يفعله هذا النظام وما لا يفعله') }}</p>
                    <p class="mt-1">{{ __('يُصدر مستندًا مطابقًا لمعيار EN 16931 بصيغة UBL 2.1، بترقيم متسلسل بلا فجوات وسلسلة بصمات تكشف أي تعديل رجعي.') }}</p>
                    <p class="mt-1">{{ __('ولا يوقّع بشهادة جهة ضريبية ولا يرسل للإقرار: الخطوتان تختلفان من دولة لأخرى وتحتاجان اعتمادات لا مكان لها هنا. ادّعاؤهما دون تنفيذهما أسوأ من عدم تقديمهما.') }}</p>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit"
                    class="flex items-center gap-2 rounded-xl bg-primary px-6 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                    <span class="material-symbols-outlined !text-xl">save</span>
                    {{ __('حفظ') }}
                </button>
            </div>
        </form>
    </div>
@endsection
