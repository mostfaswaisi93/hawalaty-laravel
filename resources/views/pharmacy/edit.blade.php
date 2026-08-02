@extends('layouts.app')

@section('title', $item->name.' - '.__('تصنيف الدواء'))

@section('content')
    <div class="page-enter space-y-6" x-data="{ submitting: false, prescription: {{ $item->is_prescription ? 'true' : 'false' }} }">
        <header>
            <a href="{{ route('pharmacy.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                {{ __('سجل الصرف') }}
            </a>
            <h1 class="mt-1 font-display-lg text-display-lg text-on-surface">{{ $item->name }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('التصنيف هو ما يقرّر ماذا يُطلب عند الصرف، وما يظهر كبديل.') }}</p>
        </header>

        <form method="POST" action="{{ route('pharmacy.update', $item->id) }}" @submit="submitting = true">
            @csrf
            @method('PUT')

            <x-card title="{{ __('التصنيف الدوائي') }}">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label for="ph-ingredient" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المادة الفعّالة') }}</label>
                        <input id="ph-ingredient" name="activeIngredient" value="{{ $item->active_ingredient }}" maxlength="255" placeholder="{{ __('مثال: باراسيتامول') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        <p class="font-body-sm text-outline">{{ __('الاسم الدولي غير المسجَّل (INN) — هو ما يجمع البدائل.') }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <label for="ph-atc" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('رمز ATC') }}</label>
                        <input id="ph-atc" name="atcCode" value="{{ $item->atc_code }}" maxlength="16" dir="ltr" placeholder="N02BE01"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 font-numeral-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        <p class="font-body-sm text-outline">{{ __('التصنيف التشريحي العلاجي الكيميائي — منظمة الصحة العالمية.') }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <label for="ph-strength" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('التركيز') }}</label>
                        <input id="ph-strength" name="strength" value="{{ $item->strength }}" maxlength="64" placeholder="500 mg" dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="ph-form" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الشكل الصيدلاني') }}</label>
                        <input id="ph-form" name="dosageForm" value="{{ $item->dosage_form }}" maxlength="64" placeholder="{{ __('مثال: أقراص') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                </div>
            </x-card>

            <x-card title="{{ __('ضوابط الصرف') }}" class="mt-6">
                <div class="space-y-4">
                    <div class="flex items-start gap-3 rounded-2xl border p-4 transition"
                        :class="prescription ? 'border-primary/50 bg-primary/5' : 'border-outline-variant'">
                        <input type="hidden" name="isPrescription" value="0" />
                        <input id="ph-rx" type="checkbox" name="isPrescription" value="1" x-model="prescription"
                            class="mt-1 h-5 w-5 flex-shrink-0 rounded border-outline-variant text-primary focus:ring-primary" />
                        <label for="ph-rx" class="min-w-0 cursor-pointer">
                            <span class="block font-body-lg font-bold text-on-surface">{{ __('دواء موصوف (Rx)') }}</span>
                            <span class="mt-0.5 block font-body-sm text-outline">{{ __('لن يُصرف بدون اسم طبيب ورقم وصفة — رفض لا تنبيه.') }}</span>
                        </label>
                    </div>

                    <div class="space-y-1.5" x-show="prescription" x-cloak>
                        <label for="ph-schedule" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('جدول الرقابة') }}</label>
                        <input id="ph-schedule" name="controlSchedule" value="{{ $item->control_schedule }}" maxlength="16" dir="ltr" placeholder="{{ __('مثال: جدول 2') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        <p class="font-body-sm text-outline">{{ __('اتركه فارغًا لغير الخاضع للرقابة. عند تعبئته يُطلب رقم هوية المريض ويُقيَّد في السجل.') }}</p>
                    </div>

                    <div class="flex items-start gap-3 rounded-2xl border border-outline-variant p-4">
                        <input type="hidden" name="coldChain" value="0" />
                        <input id="ph-cold" type="checkbox" name="coldChain" value="1" @checked($item->cold_chain)
                            class="mt-1 h-5 w-5 flex-shrink-0 rounded border-outline-variant text-primary focus:ring-primary" />
                        <label for="ph-cold" class="min-w-0 cursor-pointer">
                            <span class="block font-body-lg font-bold text-on-surface">{{ __('سلسلة تبريد (2–8 °م)') }}</span>
                            <span class="mt-0.5 block font-body-sm text-outline">{{ __('انقطاع السلسلة يُتلف الكمية — لا بد أن يظهر قبل التخزين.') }}</span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t border-outline-variant/50 pt-5">
                    <a href="{{ route('pharmacy.index') }}" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</a>
                    <button type="submit" :disabled="submitting"
                        class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                        x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ التصنيف'))">{{ __('حفظ التصنيف') }}</button>
                </div>
            </x-card>
        </form>

        @if ($substitutes->isNotEmpty())
            <x-card title="{{ __('بدائل بنفس المادة الفعّالة') }}" subtitle="{{ __('السؤال اليومي على الكاونتر.') }}">
                <ul class="divide-y divide-outline-variant/60 rounded-2xl border border-outline-variant">
                    @foreach ($substitutes as $substitute)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <p class="font-body-lg font-bold">{{ $substitute->name }}</p>
                                <p class="font-body-sm text-outline" dir="ltr">{{ $substitute->strength }} {{ $substitute->dosage_form }}</p>
                            </div>
                            <span class="font-numeral-lg font-bold" dir="ltr">{{ number_format((float) $substitute->price, 2) }} ₪</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    </div>
@endsection
