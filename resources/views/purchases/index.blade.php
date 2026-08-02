@extends('layouts.app')

@section('title', __('المشتريات - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('المشتريات') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('المدخل الوحيد الشرعي للمخزون — ومنه تُحسب التكلفة.') }}</p>
            </div>
            <a href="{{ route('purchases.create') }}"
                class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                <span class="material-symbols-outlined !text-xl">add</span>
                {{ __('أمر شراء جديد') }}
            </a>
        </header>

        <form method="GET" action="{{ route('purchases.index') }}" class="reveal-up flex flex-col gap-3 sm:flex-row" style="--d:60ms">
            <x-keep-per-page />
            <div class="relative flex-1 sm:max-w-md">
                <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">search</span>
                <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('ابحث برقم الأمر أو الفاتورة...') }}" aria-label="{{ __('بحث') }}"
                    class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
            </div>
            <select name="status" onchange="this.form.submit()" aria-label="{{ __('الحالة') }}"
                class="rounded-2xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                <option value="">{{ __('كل الحالات') }}</option>
                <option value="draft" @selected(($status ?? '') === 'draft')>{{ __('مسودة') }}</option>
                <option value="received" @selected(($status ?? '') === 'received')>{{ __('مستلمة') }}</option>
                <option value="cancelled" @selected(($status ?? '') === 'cancelled')>{{ __('ملغاة') }}</option>
            </select>
        </form>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($purchases->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">local_shipping</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد أوامر شراء بعد') }}</p>
                </div>
            @else
                @php
                    $badge = fn ($s) => match ($s) {
                        'received' => 'bg-success/10 text-success',
                        'cancelled' => 'bg-error/10 text-error',
                        default => 'bg-secondary-container/30 text-secondary',
                    };
                @endphp

                <x-table-responsive :headers="[__('الأمر'), __('المورد'), __('الموقع'), ['label' => __('الأصناف'), 'align' => 'end'], ['label' => __('الإجمالي'), 'align' => 'end'], __('الحالة'), __('')]">
                    <x-slot:table>
                        @foreach ($purchases as $purchase)
                            <tr class="reveal-up transition-colors hover:bg-surface-container-low/50" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <td class="px-4 py-3">
                                    <p class="font-body-sm font-bold" dir="ltr">{{ $purchase->reference ?: mb_substr($purchase->id, -8) }}</p>
                                    <p class="font-body-sm text-outline">{{ $purchase->ordered_at?->translatedFormat('j M Y') }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm">{{ $purchase->supplier?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $purchase->location?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg">{{ $purchase->lines_count }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold" dir="ltr">{{ number_format((float) $purchase->total, 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="rounded-lg px-2 py-1 font-body-sm font-bold {{ $badge($purchase->status) }}">{{ $purchase->statusLabel() }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('purchases.show', $purchase->id) }}"
                                        class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                                        aria-label="{{ __('فتح أمر الشراء') }}">
                                        <span class="material-symbols-outlined !text-xl rtl:-scale-x-100">chevron_right</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($purchases as $purchase)
                            <a href="{{ route('purchases.show', $purchase->id) }}" class="reveal-up block rounded-2xl border border-outline-variant bg-surface-container-lowest p-4" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-body-lg font-bold" dir="ltr">{{ $purchase->reference ?: mb_substr($purchase->id, -8) }}</p>
                                        <p class="font-body-sm text-outline">{{ $purchase->supplier?->name ?? '—' }}</p>
                                    </div>
                                    <span class="whitespace-nowrap rounded-lg px-2 py-1 font-body-sm font-bold {{ $badge($purchase->status) }}">{{ $purchase->statusLabel() }}</span>
                                </div>
                                <p class="mt-2 font-numeral-lg font-bold" dir="ltr">{{ number_format((float) $purchase->total, 2) }} ₪</p>
                            </a>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$purchases" :perPage="$perPage" />
            @endif
        </section>
    </div>
@endsection
