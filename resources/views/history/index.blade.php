@extends('layouts.app')

@section('title', __('سجل الطلبات - حوالاتي'))

@section('content')
    @php
        $statusPill = fn ($status) => match ($status->value) {
            'completed' => 'bg-success-container text-success dark:bg-success-container/15',
            'pending' => 'bg-secondary-container/20 text-secondary',
            default => 'bg-error-container text-error dark:bg-error/15',
        };
        $statusDot = fn ($status) => match ($status->value) {
            'completed' => 'bg-green-500',
            'pending' => 'bg-secondary-container',
            default => 'bg-error',
        };
        $statusLabel = fn ($status) => match ($status->value) {
            'completed' => __('مكتملة'),
            'pending' => __('قيد الانتظار'),
            default => __('فاشلة'),
        };
        $hasFilters = ! empty($filters);
    @endphp

    <div class="page-enter space-y-6">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('سجل الطلبات') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">
                    {{ $transfers->total() }} {{ __('طلب - تصفّح وابحث في كل معاملاتك.') }}
                </p>
            </div>
            <a href="/transfers" class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95" title="{{ __('طلب جديد') }}">
                <span class="material-symbols-outlined !text-xl">add</span>
                {{ __('طلب جديد') }}
            </a>
        </header>

        {{-- ══════════════ filters ══════════════ --}}
        <form method="GET" action="{{ route('history.index') }}" x-data="{ submitting: false }" @submit="submitting = true"
            class="reveal-up tonal-elevation grid grid-cols-1 gap-4 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 sm:grid-cols-2 lg:grid-cols-5" style="--d: 40ms">
            <div class="space-y-1.5 sm:col-span-2 lg:col-span-1">
                <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('بحث') }}</label>
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-3' : 'left-0 pl-3' }}">search</span>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('اسم العميل...') }}"
                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-10 pl-3' : 'pl-10 pr-3' }}" />
                </div>
            </div>

            <div class="space-y-1.5">
                <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الحالة') }}</label>
                <select name="status" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                    <option value="">{{ __('الكل') }}</option>
                    <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>{{ __('مكتملة') }}</option>
                    <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>{{ __('قيد الانتظار') }}</option>
                    <option value="failed" @selected(($filters['status'] ?? '') === 'failed')>{{ __('فاشلة') }}</option>
                </select>
            </div>

            <div class="space-y-1.5">
                <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('طريقة الدفع') }}</label>
                <select name="paymentMethod" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                    <option value="">{{ __('الكل') }}</option>
                    <option value="cash" @selected(($filters['paymentMethod'] ?? '') === 'cash')>{{ __('نقدي') }}</option>
                    <option value="bank" @selected(($filters['paymentMethod'] ?? '') === 'bank')>{{ __('تحويل بنكي') }}</option>
                </select>
            </div>

            <div class="space-y-1.5">
                <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('من تاريخ') }}</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" dir="ltr"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
            </div>

            <div class="space-y-1.5">
                <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('إلى تاريخ') }}</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" dir="ltr"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
            </div>

            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-5">
                <button type="submit" :disabled="submitting" class="flex items-center gap-2 rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50">
                    <span class="material-symbols-outlined !text-xl" :class="submitting && 'animate-spin'" x-text="submitting ? 'progress_activity' : 'filter_alt'"></span>
                    <span x-text="submitting ? '{{ __('جارٍ التطبيق...') }}' : '{{ __('تطبيق') }}'"></span>
                </button>
                @if ($hasFilters)
                    <a href="{{ route('history.index') }}" class="flex items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-6 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container" title="{{ __('مسح') }}">
                        <span class="material-symbols-outlined !text-xl">close</span>
                        {{ __('مسح') }}
                    </a>
                @endif
            </div>
        </form>

        {{-- ══════════════ results ══════════════ --}}
        <section class="reveal-up tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6" style="--d: 140ms">
            @if ($transfers->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-20 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">{{ $hasFilters ? 'search_off' : 'receipt_long' }}</span>
                    <p class="font-body-lg text-on-surface-variant">{{ $hasFilters ? __('لا توجد طلبات مطابقة للفلاتر') : __('لا توجد طلبات بعد') }}</p>
                    @if ($hasFilters)
                        <a href="{{ route('history.index') }}" class="font-body-sm font-bold text-primary hover:underline" title="{{ __('مسح الفلاتر') }}">{{ __('مسح الفلاتر') }}</a>
                    @endif
                </div>
            @else
                <x-table-responsive :headers="[__('العميل'), __('الصنف'), __('التاريخ'), __('الحالة'), __('طريقة الدفع'), ['label' => __('المبلغ'), 'align' => 'end'], '']">
                    <x-slot:table>
                        @foreach ($transfers as $t)
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                            <span class="material-symbols-outlined !text-xl">{{ $t->payment_method->value === 'cash' ? 'payments' : 'account_balance' }}</span>
                                        </div>
                                        <p class="whitespace-nowrap text-body-sm font-bold">{{ $t->customer }}</p>
                                    </div>
                                </td>
                                <td class="max-w-xs truncate px-4 py-3 text-body-sm text-on-surface-variant">{{ $t->item_name ?: '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-body-sm text-on-surface-variant">{{ $t->created_at->translatedFormat('j F Y') }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-[11px] font-bold {{ $statusPill($t->status) }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $statusDot($t->status) }}"></span>
                                        {{ $statusLabel($t->status) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-body-sm text-on-surface-variant">
                                    {{ $t->payment_method->value === 'cash' ? __('نقدي') : ($t->bank?->name ?? __('تحويل بنكي')) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-bold text-primary">
                                    <span dir="ltr">+{{ number_format($t->amount, 2) }} ₪</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('transfers.receipt', $t->id) }}" target="_blank" aria-label="{{ __('طباعة الإيصال') }}"
                                        class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary">
                                        <span class="material-symbols-outlined !text-xl">print</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>
                    <x-slot:cards>
                        @foreach ($transfers as $t)
                            {{-- Cascade is per-card, not a container .stagger - the cards
                                 container itself belongs to the shared table component. --}}
                            <div class="reveal-up tonal-elevation rounded-2xl border border-outline-variant bg-surface-container-lowest p-4"
                                style="--d: {{ min(40 + $loop->index * 40, 480) }}ms">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                        <span class="material-symbols-outlined !text-xl">{{ $t->payment_method->value === 'cash' ? 'payments' : 'account_balance' }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-body-sm font-bold">{{ $t->customer }}</p>
                                        <p class="truncate text-[11px] text-on-surface-variant">{{ $t->payment_method->value === 'cash' ? __('نقدي') : $t->bank?->name }}</p>
                                    </div>
                                    <p class="whitespace-nowrap font-bold text-primary"><span dir="ltr">+{{ number_format($t->amount, 2) }} ₪</span></p>
                                </div>
                                <div class="mt-2.5 flex items-center justify-between gap-2 ps-[52px]">
                                    <span class="truncate text-[11px] text-on-surface-variant">{{ $t->item_name ?: '-' }} · {{ $t->created_at->translatedFormat('j M') }}</span>
                                    <div class="flex flex-shrink-0 items-center gap-2">
                                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $statusPill($t->status) }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $statusDot($t->status) }}"></span>
                                            {{ $statusLabel($t->status) }}
                                        </span>
                                        <a href="{{ route('transfers.receipt', $t->id) }}" target="_blank" aria-label="{{ __('طباعة الإيصال') }}"
                                            class="flex h-7 w-7 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary">
                                            <span class="material-symbols-outlined !text-lg">print</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$transfers" :perPage="$perPage" />

                {{-- pagination --}}
                @if ($transfers->hasPages())
                    <div class="mt-6 flex items-center justify-between gap-4 border-t border-outline-variant/50 pt-5">
                        <span class="font-body-sm text-on-surface-variant">
                            {{ __('صفحة') }} {{ $transfers->currentPage() }} {{ __('من') }} {{ $transfers->lastPage() }}
                        </span>
                        <div class="flex items-center gap-2">
                            @if ($transfers->onFirstPage())
                                <span class="flex items-center gap-1 rounded-xl border border-outline-variant/60 px-4 py-2 font-body-sm text-outline/60">
                                    <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span> {{ __('السابق') }}
                                </span>
                            @else
                                <a href="{{ $transfers->previousPageUrl() }}" class="flex items-center gap-1 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container">
                                    <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span> {{ __('السابق') }}
                                </a>
                            @endif

                            @if ($transfers->hasMorePages())
                                <a href="{{ $transfers->nextPageUrl() }}" class="flex items-center gap-1 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container">
                                    {{ __('التالي') }} <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_forward</span>
                                </a>
                            @else
                                <span class="flex items-center gap-1 rounded-xl border border-outline-variant/60 px-4 py-2 font-body-sm text-outline/60">
                                    {{ __('التالي') }} <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_forward</span>
                                </span>
                            @endif
                        </div>
                    </div>
                @endif
            @endif
        </section>
    </div>
@endsection
