@extends('layouts.app')

@section('title', 'التقارير — حوالاتي')

@section('content')
    @php
        $statusLabel = fn ($status) => match ($status->value) {
            'completed' => 'مكتملة',
            'pending' => 'قيد الانتظار',
            default => 'فاشلة',
        };
        $maxDaily = $daily->max() ?: 1;
        $dayLabels = ['أحد', 'اثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'];
    @endphp

    <div class="page-enter space-y-6">
        <header>
            <h1 class="font-display-lg text-display-lg text-on-surface">التقارير</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">تحليل أداء عملك خلال فترة محددة.</p>
        </header>

        {{-- date range --}}
        <form method="GET" action="{{ route('reports.index') }}"
            class="tonal-elevation flex flex-col gap-4 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 sm:flex-row sm:items-end">
            <div class="flex-1 space-y-1.5">
                <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">من تاريخ</label>
                <input type="date" name="from" value="{{ $from }}" dir="ltr"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
            </div>
            <div class="flex-1 space-y-1.5">
                <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">إلى تاريخ</label>
                <input type="date" name="to" value="{{ $to }}" dir="ltr"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
            </div>
            <button type="submit" class="flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                <span class="material-symbols-outlined !text-xl">query_stats</span>
                عرض
            </button>
        </form>

        {{-- summary cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach ([
                ['receipt_long', 'primary', 'عدد الطلبات', number_format($count), ''],
                ['payments', 'secondary', 'إجمالي الإيرادات', number_format($total, 2), '₪'],
                ['trending_up', 'primary', 'متوسط الطلب', number_format($average, 2), '₪'],
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
        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-6">
            <h2 class="mb-6 font-headline-md text-headline-md text-on-surface">الإيراد اليومي</h2>
            @if ($daily->isEmpty())
                <div class="flex flex-col items-center gap-2 py-12 text-center text-outline">
                    <span class="material-symbols-outlined !text-4xl">bar_chart</span>
                    <p class="font-body-sm">لا نشاط في هذه الفترة</p>
                </div>
            @else
                <div class="flex h-56 items-end gap-2 overflow-x-auto pb-2">
                    @foreach ($daily as $date => $sum)
                        <div class="flex min-w-10 flex-1 flex-col items-center gap-2">
                            <span class="font-numeral-lg text-[10px] text-on-surface-variant" dir="ltr">{{ number_format($sum, 0) }}</span>
                            <div class="flex w-full items-end" style="height: 160px">
                                <div class="w-full rounded-t-lg bg-gradient-to-t from-primary to-primary-container transition-all" style="height: {{ max(4, ($sum / $maxDaily) * 100) }}%" title="{{ number_format($sum, 2) }} ₪"></div>
                            </div>
                            <span class="font-label-caps text-[10px] text-outline">{{ $dayLabels[\Illuminate\Support\Carbon::parse($date)->dayOfWeek] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- transfers in range --}}
        @if ($transfers->isNotEmpty())
            <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                <h2 class="mb-4 font-headline-md text-headline-md text-on-surface">طلبات الفترة ({{ number_format($count) }})</h2>
                <x-table-responsive :headers="['العميل', 'التاريخ', 'الحالة', 'المبلغ']">
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
                            <div class="flex items-center justify-between gap-2 rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="min-w-0">
                                    <p class="truncate font-body-sm font-bold">{{ $t->customer }}</p>
                                    <p class="text-[11px] text-outline">{{ $t->created_at->translatedFormat('j M') }} · {{ $statusLabel($t->status) }}</p>
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
