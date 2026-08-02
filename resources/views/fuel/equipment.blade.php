@extends('layouts.app')

@section('title', __('الخزانات والأسعار - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6" x-data="{ tankOpen: {{ $errors->any() ? 'true' : 'false' }}, pumpFor: null, priceOpen: false, submitting: false }">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <a href="{{ route('fuel.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                    <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                    {{ __('الورديات والخزانات') }}
                </a>
                <h1 class="mt-1 font-display-lg text-display-lg text-on-surface">{{ __('الخزانات والأسعار') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('كل مضخة تتبع خزانًا، وكل خزان يحمل صنف وقود واحدًا.') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="priceOpen = true" @disabled($grades->isEmpty())
                    class="flex items-center gap-2 rounded-xl bg-surface-container px-5 py-2.5 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high disabled:cursor-not-allowed disabled:opacity-50">
                    <span class="material-symbols-outlined !text-xl">payments</span>
                    {{ __('سعر جديد') }}
                </button>
                <button type="button" @click="tankOpen = true" @disabled($grades->isEmpty())
                    class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50">
                    <span class="material-symbols-outlined !text-xl">add</span>
                    {{ __('خزان جديد') }}
                </button>
            </div>
        </header>

        @if ($grades->isEmpty())
            <div class="flex items-start gap-3 rounded-2xl border border-secondary-container/40 bg-secondary-container/10 p-4 text-secondary">
                <span class="material-symbols-outlined flex-shrink-0">info</span>
                <p class="font-body-sm">{{ __('أضف صنف وقود بوحدة «لتر» من صفحة الأصناف أولًا.') }}</p>
            </div>
        @endif

        <div class="space-y-4">
            @forelse ($tanks as $tank)
                <x-card class="reveal-up" style="--d:{{ min($loop->index, 11) * 40 + 60 }}ms">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="font-headline-md text-headline-md">{{ $tank->name }}</h3>
                            <p class="mt-1 font-body-sm text-outline">
                                {{ $tank->item?->name }}
                                @if ((float) $tank->capacity > 0)
                                    · <span dir="ltr">{{ number_format((float) $tank->capacity, 0) }} {{ __('لتر') }}</span>
                                @endif
                            </p>
                        </div>
                        <button type="button" @click="pumpFor = @js($tank->id)"
                            class="flex flex-shrink-0 items-center gap-1.5 rounded-xl bg-surface-container px-4 py-2 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                            <span class="material-symbols-outlined !text-lg">add</span>{{ __('مضخة') }}
                        </button>
                    </div>

                    @if ($tank->pumps->isNotEmpty())
                        <ul class="mt-4 divide-y divide-outline-variant/60 rounded-2xl border border-outline-variant">
                            @foreach ($tank->pumps as $pump)
                                <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                                    <span class="font-body-lg">{{ $pump->name }}</span>
                                    <span class="font-body-sm text-outline" dir="ltr">
                                        @if ($pump->seal_number)
                                            {{ __('ختم') }} {{ $pump->seal_number }}
                                        @endif
                                        @if ($pump->calibrated_at)
                                            · {{ $pump->calibrated_at->format('Y-m-d') }}
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            @empty
                <div class="reveal-scale flex flex-col items-center gap-3 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">oil_barrel</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد خزانات بعد') }}</p>
                </div>
            @endforelse
        </div>

        <x-card title="{{ __('سجل الأسعار') }}" subtitle="{{ __('السعر يُحفظ بتاريخ سريانه — وردية قديمة تبقى مقروءة بالسعر الذي بيعت به فعلًا.') }}">
            @if ($prices->isEmpty())
                <p class="py-8 text-center font-body-sm text-on-surface-variant">{{ __('لا توجد أسعار مسجّلة') }}</p>
            @else
                <ul class="divide-y divide-outline-variant/60 rounded-2xl border border-outline-variant">
                    @foreach ($prices as $price)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <p class="font-body-lg font-bold">{{ $price->item?->name }}</p>
                                <p class="font-body-sm text-outline" dir="ltr">{{ $price->effective_from?->format('Y-m-d') }}</p>
                            </div>
                            <span class="font-numeral-lg font-bold" dir="ltr">{{ number_format((float) $price->price, 3) }} ₪</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <div x-show="tankOpen" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="tank-title">
            <div class="fixed inset-0 bg-black/40" x-show="tankOpen" x-transition.opacity @click="tankOpen = false"></div>
            <div class="relative z-10 w-full max-w-md rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                x-show="tankOpen" @keydown.escape.window="tankOpen = false"
                x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

                <x-modal-close on-close="tankOpen = false" />

                <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
                    <h3 id="tank-title" class="font-headline-md text-headline-md">{{ __('خزان جديد') }}</h3>
                </div>

                <form method="POST" action="{{ route('fuel.tanks.store') }}" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf

                    <div class="space-y-1.5">
                        <label for="tank-name" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الاسم') }}</label>
                        <input id="tank-name" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="{{ __('مثال: خزان 1') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        @error('name')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="tank-item" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('صنف الوقود') }}</label>
                        <select id="tank-item" name="itemId" required
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            @foreach ($grades as $grade)
                                <option value="{{ $grade->id }}" @selected(old('itemId') === $grade->id)>{{ $grade->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label for="tank-capacity" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('السعة') }}</label>
                            <input id="tank-capacity" type="number" step="0.001" min="0" name="capacity" value="{{ old('capacity') }}" dir="ltr"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        </div>
                        <div class="space-y-1.5">
                            <label for="tank-min" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الحد الأدنى') }}</label>
                            <input id="tank-min" type="number" step="0.001" min="0" name="minimumLevel" value="{{ old('minimumLevel') }}" dir="ltr"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            <p class="font-body-sm text-outline">{{ __('تحته تسحب المضخات رواسب.') }}</p>
                        </div>
                    </div>

                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="tankOpen = false" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                            x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ'))">{{ __('حفظ') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="pumpFor" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="pump-title">
            <div class="fixed inset-0 bg-black/40" x-show="pumpFor" x-transition.opacity @click="pumpFor = null"></div>
            <div class="relative z-10 w-full max-w-md rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                x-show="pumpFor" @keydown.escape.window="pumpFor = null"
                x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

                <x-modal-close on-close="pumpFor = null" />

                <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
                    <h3 id="pump-title" class="font-headline-md text-headline-md">{{ __('مضخة جديدة') }}</h3>
                </div>

                <form method="POST" :action="'/fuel/tanks/' + pumpFor + '/pumps'" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf

                    <div class="space-y-1.5">
                        <label for="pump-name" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الاسم') }}</label>
                        <input id="pump-name" name="name" required maxlength="255" placeholder="{{ __('مثال: مضخة 1 - فوهة أ') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="pump-seal" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('رقم الختم') }}</label>
                        <input id="pump-seal" name="sealNumber" maxlength="64" dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        <p class="font-body-sm text-outline">{{ __('كسر الختم يُبطل المعايرة — الرقم يستحق الحفظ.') }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <label for="pump-calibrated" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('تاريخ المعايرة') }}</label>
                        <input id="pump-calibrated" type="date" name="calibratedAt" dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="pumpFor = null" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                            x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ'))">{{ __('حفظ') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="priceOpen" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="price-title">
            <div class="fixed inset-0 bg-black/40" x-show="priceOpen" x-transition.opacity @click="priceOpen = false"></div>
            <div class="relative z-10 w-full max-w-md rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                x-show="priceOpen" @keydown.escape.window="priceOpen = false"
                x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

                <x-modal-close on-close="priceOpen = false" />

                <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
                    <h3 id="price-title" class="font-headline-md text-headline-md">{{ __('سعر جديد') }}</h3>
                    <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">{{ __('ثلاث خانات عشرية — التقريب لخانتين يحرّك مالًا حقيقيًا.') }}</p>
                </div>

                <form method="POST" action="{{ route('fuel.prices.store') }}" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf

                    <div class="space-y-1.5">
                        <label for="price-item" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('صنف الوقود') }}</label>
                        <select id="price-item" name="itemId" required
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            @foreach ($grades as $grade)
                                <option value="{{ $grade->id }}">{{ $grade->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label for="price-value" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('السعر لكل لتر') }}</label>
                        <input id="price-value" type="number" step="0.001" min="0" name="price" required dir="ltr" placeholder="6.475"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 font-numeral-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="price-from" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('يسري من') }}</label>
                        <input id="price-from" type="date" name="effectiveFrom" value="{{ now()->toDateString() }}" required dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="priceOpen = false" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                            x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ'))">{{ __('حفظ') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
