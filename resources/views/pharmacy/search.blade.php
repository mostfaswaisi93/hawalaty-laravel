@extends('layouts.app')

@section('title', __('بحث بالمادة الفعّالة - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6">
        <header>
            <a href="{{ route('pharmacy.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                {{ __('سجل الصرف') }}
            </a>
            <h1 class="mt-1 font-display-lg text-display-lg text-on-surface">{{ __('بحث بالمادة الفعّالة') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('بالاسم التجاري أو المادة الفعّالة أو رمز ATC — الكاونتر يصل للدواء نفسه من الثلاثة.') }}</p>
        </header>

        <form method="GET" action="{{ route('pharmacy.search') }}" class="reveal-up relative" style="--d:60ms">
            <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">search</span>
            <input type="text" name="term" value="{{ $term }}" placeholder="{{ __('مثال: باراسيتامول أو N02BE01') }}" aria-label="{{ __('بحث') }}" autofocus
                class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-4 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
        </form>

        @if ($term !== '')
            <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                @if ($results->isEmpty())
                    <div class="flex flex-col items-center gap-3 py-12 text-center">
                        <span class="material-symbols-outlined !text-5xl text-outline/50">search_off</span>
                        <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد نتائج') }}</p>
                    </div>
                @else
                    <ul class="divide-y divide-outline-variant/60 rounded-2xl border border-outline-variant">
                        @foreach ($results as $medicine)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                                <div class="min-w-0">
                                    <p class="font-body-lg font-bold">
                                        {{ $medicine->name }}
                                        @if ($medicine->is_prescription)
                                            <span class="ms-1 rounded bg-error/10 px-1.5 py-0.5 font-body-sm text-error">Rx</span>
                                        @endif
                                        @if ($medicine->control_schedule)
                                            <span class="ms-1 rounded bg-error/10 px-1.5 py-0.5 font-body-sm text-error" dir="ltr">{{ $medicine->control_schedule }}</span>
                                        @endif
                                        @if ($medicine->cold_chain)
                                            <span class="ms-1 material-symbols-outlined !text-base align-middle text-primary" title="{{ __('سلسلة تبريد') }}">ac_unit</span>
                                        @endif
                                    </p>
                                    <p class="font-body-sm text-outline" dir="ltr">
                                        {{ $medicine->active_ingredient }}
                                        @if ($medicine->strength) · {{ $medicine->strength }} @endif
                                        @if ($medicine->atc_code) · {{ $medicine->atc_code }} @endif
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-numeral-lg font-bold" dir="ltr">{{ number_format((float) $medicine->price, 2) }} ₪</span>
                                    <a href="{{ route('pharmacy.edit', $medicine->id) }}"
                                        class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                                        aria-label="{{ __('تصنيف :item', ['item' => $medicine->name]) }}">
                                        <span class="material-symbols-outlined !text-xl">edit</span>
                                    </a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif
    </div>
@endsection
