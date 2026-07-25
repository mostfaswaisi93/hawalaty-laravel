@extends('layouts.app')

@section('title', __('التقارير - حوالاتي'))

@section('content')
    @php
        $statusLabel = fn ($status) => match ($status->value) {
            'completed' => __('مكتملة'),
            'pending' => __('قيد الانتظار'),
            default => __('فاشلة'),
        };
        $maxDaily = $daily->max() ?: 1;
        $dayLabels = [__('أحد'), __('اثنين'), __('ثلاثاء'), __('أربعاء'), __('خميس'), __('جمعة'), __('سبت')];
    @endphp

    <div class="page-enter space-y-6">
        <header>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('التقارير') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('تحليل أداء عملك خلال فترة محددة.') }}</p>
        </header>

        {{-- date range --}}
        <form method="GET" action="{{ route('reports.index') }}" x-data="{ submitting: false }" @submit="submitting = true"
            class="reveal-up tonal-elevation flex flex-col gap-4 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 sm:flex-row sm:items-end" style="--d: 40ms">
            <div class="flex-1 space-y-1.5">
                <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('من تاريخ') }}</label>
                <input type="date" name="from" value="{{ $from }}" dir="ltr"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
            </div>
            <div class="flex-1 space-y-1.5">
                <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('إلى تاريخ') }}</label>
                <input type="date" name="to" value="{{ $to }}" dir="ltr"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
            </div>
            <button type="submit" :disabled="submitting" class="flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50">
                <span class="material-symbols-outlined !text-xl" :class="submitting && 'animate-spin'" x-text="submitting ? 'progress_activity' : 'query_stats'"></span>
                <span x-text="submitting ? '{{ __('جارٍ العرض...') }}' : '{{ __('عرض') }}'"></span>
            </button>
            <a href="{{ route('reports.export', ['from' => $from, 'to' => $to]) }}" target="_blank"
                class="flex items-center justify-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-6 py-2.5 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container">
                <span class="material-symbols-outlined !text-xl">picture_as_pdf</span>
                {{ __('تصدير PDF') }}
            </a>
        </form>

        {{-- summary cards --}}
        <div class="stagger grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach ([
                ['receipt_long', 'primary', __('عدد الطلبات'), number_format($count), ''],
                ['payments', 'secondary', __('إجمالي الإيرادات'), number_format($total, 2), '₪'],
                ['trending_up', 'primary', __('متوسط الطلب'), number_format($average, 2), '₪'],
            ] as [$icon, $accent, $label, $value, $unit])
                <div class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-6">
                    <div class="flex items-center justify-between">
                        <p class="font-body-sm text-on-surface-variant">{{ $label }}</p>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $accent === 'primary' ? 'bg-primary/10 text-primary' : 'bg-secondary-container/15 text-secondary' }}">
                            <span class="material-symbols-outlined">{{ $icon }}</span>
                        </div>
                    </div>
                    <p class="mt-3 font-display-lg text-3xl font-bold text-on-surface" dir="ltr">{{ $value }} <span class="text-xl text-on-surface-variant">{{ $unit }}</span></p>
                </div>
            @endforeach
        </div>

        {{-- daily revenue chart --}}
        <section class="reveal-up tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-6" style="--d: 140ms">
            <h2 class="mb-6 font-headline-md text-headline-md text-on-surface">{{ __('الإيراد اليومي') }}</h2>
            @if ($daily->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-2 py-12 text-center text-outline">
                    <span class="material-symbols-outlined !text-4xl">bar_chart</span>
                    <p class="font-body-sm">{{ __('لا نشاط في هذه الفترة') }}</p>
                </div>
            @else
                <div class="relative"
                    x-data="{ overflowing: false }"
                    x-init="$nextTick(() => { overflowing = $refs.chartScroll.scrollWidth > $refs.chartScroll.clientWidth })">
                    <div x-show="overflowing" x-cloak class="pointer-events-none absolute inset-y-0 end-0 z-10 w-10 bg-gradient-to-l from-surface-container-lowest to-transparent rtl:bg-gradient-to-r"></div>
                    <div class="flex h-56 items-end gap-2 overflow-x-auto pb-2" x-ref="chartScroll">
                    @foreach ($daily as $date => $sum)
                        <div class="flex min-w-10 flex-1 flex-col items-center gap-2">
                            <span class="font-numeral-lg text-[10px] text-on-surface-variant" dir="ltr">{{ number_format($sum, 0) }}</span>
                            <div class="flex w-full items-end" style="height: 160px">
                                {{-- Delay is capped: a wide date range would otherwise trail
                                     the last columns well past a second. --}}
                                <div class="grow-bar w-full rounded-t-lg bg-primary transition-all" style="height: {{ max(4, ($sum / $maxDaily) * 100) }}%; --d: {{ min(180 + $loop->index * 35, 560) }}ms" title="{{ number_format($sum, 2) }} ₪"></div>
                            </div>
                            <span class="font-label-caps text-[10px] text-on-surface-variant">{{ $dayLabels[\Illuminate\Support\Carbon::parse($date)->dayOfWeek] }}</span>
                        </div>
                    @endforeach
                    </div>
                </div>
            @endif
        </section>

        {{-- transfers in range --}}
        @if ($transfers->isNotEmpty())
            <section class="reveal-up tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6" style="--d: 220ms">
                <h2 class="mb-4 font-headline-md text-headline-md text-on-surface">{{ __('طلبات الفترة') }} ({{ number_format($count) }})</h2>
                <x-table-responsive :headers="[__('العميل'), __('التاريخ'), __('الحالة'), __('المبلغ')]">
                    <x-slot:table>
                        @foreach ($transfers->sortByDesc('created_at') as $t)
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="whitespace-nowrap px-4 py-3 text-body-sm font-bold">{{ $t->customer }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-body-sm text-on-surface-variant">{{ $t->created_at->translatedFormat('j F Y') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-body-sm text-on-surface-variant">{{ $statusLabel($t->status) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-bold text-primary"><span dir="ltr">+{{ number_format($t->amount, 2) }} ₪</span></td>
                            </tr>
                        @endforeach
                    </x-slot:table>
                    <x-slot:cards>
                        @foreach ($transfers->sortByDesc('created_at') as $t)
                            {{-- Cascade is per-card, not a container .stagger - the cards
                                 container itself belongs to the shared table component. --}}
                            <div class="reveal-up flex items-center justify-between gap-2 rounded-2xl border border-outline-variant bg-surface-container-lowest p-4"
                                style="--d: {{ min(40 + $loop->index * 40, 480) }}ms">
                                <div class="min-w-0">
                                    <p class="truncate font-body-sm font-bold">{{ $t->customer }}</p>
                                    <p class="text-[11px] text-on-surface-variant">{{ $t->created_at->translatedFormat('j M') }} · {{ $statusLabel($t->status) }}</p>
                                </div>
                                <p class="whitespace-nowrap font-bold text-primary"><span dir="ltr">+{{ number_format($t->amount, 2) }} ₪</span></p>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>
            </section>
        @endif
    </div>
@endsection
