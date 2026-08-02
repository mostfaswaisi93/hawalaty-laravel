@extends('layouts.app')

@section('title', __('بحث برقم تسلسلي - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6">
        <header>
            <a href="{{ route('warranty.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                {{ __('الضمان والصيانة') }}
            </a>
            <h1 class="mt-1 font-display-lg text-display-lg text-on-surface">{{ __('بحث برقم تسلسلي') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('امسح الرقم لتعرف فورًا إن كانت الوحدة مبيعة من عندك وهل ضمانها ساري.') }}</p>
        </header>

        <form method="GET" action="{{ route('warranty.lookup') }}" class="reveal-up relative" style="--d:60ms">
            <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">qr_code_scanner</span>
            <input type="text" name="serial" value="{{ $serial }}" placeholder="{{ __('الرقم التسلسلي / IMEI') }}" aria-label="{{ __('الرقم التسلسلي') }}" autofocus dir="ltr"
                class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-4 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
        </form>

        @if ($serial !== '')
            <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                @if ($matches->isEmpty())
                    <div class="flex flex-col items-center gap-3 py-12 text-center">
                        <span class="material-symbols-outlined !text-5xl text-error/60">search_off</span>
                        <p class="font-body-lg font-bold text-on-surface">{{ __('لا توجد وحدة بهذا الرقم') }}</p>
                        <p class="max-w-md font-body-sm text-on-surface-variant">{{ __('هذه الوحدة ليست مبيعة من عندك — أو لم تُسجَّل أرقامها عند الاستلام.') }}</p>
                    </div>
                @else
                    <ul class="space-y-3">
                        @foreach ($matches as $unit)
                            <li class="rounded-2xl border border-outline-variant p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-body-lg font-bold" dir="ltr">{{ $unit->serial }}</p>
                                        <p class="font-body-sm text-on-surface-variant">{{ $unit->item?->name }}</p>
                                        <p class="mt-1 font-body-sm text-outline">
                                            {{ $unit->sold_at
                                                ? __('بيعت في :date لـ :customer', [
                                                    'date' => $unit->sold_at->translatedFormat('j M Y'),
                                                    'customer' => $unit->customer?->name ?? $unit->transfer?->customer ?? '—',
                                                ])
                                                : __('لم تُبَع بعد') }}
                                        </p>
                                    </div>
                                    <div class="flex flex-col items-end gap-2">
                                        <span class="whitespace-nowrap rounded-lg px-2 py-1 font-body-sm font-bold
                                            {{ $unit->underWarranty() ? 'bg-success/10 text-success' : ($unit->hasWarranty() ? 'bg-error/10 text-error' : 'bg-surface-container text-on-surface-variant') }}">
                                            {{ $unit->warrantyLabel() }}
                                        </span>
                                        @if ($unit->warranty_ends_at)
                                            <span class="font-body-sm text-outline" dir="ltr">{{ $unit->warranty_ends_at->format('Y-m-d') }}</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-2 border-t border-outline-variant/50 pt-3">
                                    <a href="{{ route('serials.show', $unit->id) }}"
                                        class="rounded-xl bg-surface-container px-4 py-2 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                                        {{ __('تتبّع الوحدة') }}
                                    </a>
                                    <form method="POST" action="{{ route('warranty.store') }}" class="contents">
                                        @csrf
                                        <input type="hidden" name="serialId" value="{{ $unit->id }}" />
                                        <input type="hidden" name="itemId" value="{{ $unit->menu_item_id }}" />
                                        <input type="hidden" name="customerName" value="{{ $unit->customer?->name ?? $unit->transfer?->customer ?? __('عميل') }}" />
                                        <input type="hidden" name="issue" value="{{ __('بانتظار التشخيص') }}" />
                                        <button type="submit"
                                            class="rounded-xl bg-primary px-4 py-2 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                                            {{ __('فتح مطالبة لهذه الوحدة') }}
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif
    </div>
@endsection
