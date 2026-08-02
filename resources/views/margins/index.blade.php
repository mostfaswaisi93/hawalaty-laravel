@extends('layouts.app')

@section('title', __('الهامش والتسعير - حوالاتي'))

@section('content')
    @php
        $tone = fn (?float $percent) => match (true) {
            $percent === null => 'text-outline',
            $percent < 0 => 'text-error',
            $percent < 15 => 'text-tertiary',
            default => 'text-success',
        };

        $basisLabel = fn (string $basis) => match ($basis) {
            'recipe' => __('تكلفة نظرية من الوصفة'),
            'standard' => __('تكلفة الصنف الحالية'),
            'none' => __('بلا تكلفة مسجّلة'),
            default => __('من دفتر الحركات'),
        };
    @endphp

    <div class="page-enter space-y-6">
        <header>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الهامش والتسعير') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('المبيعات رقم يقوله أي صندوق. الباقي بعد تكلفة البضاعة هو السؤال الأصعب — وهذا جوابه.') }}</p>
        </header>

        @include('margins._tabs', ['active' => 'margins'])

        <form method="GET" action="{{ route('margins.index') }}" class="reveal-up flex flex-col gap-3 sm:flex-row sm:items-end" style="--d:80ms">
            <input type="hidden" name="view" value="{{ $view }}" />
            <div class="flex-1 space-y-1.5">
                <label for="from" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('من') }}</label>
                <input id="from" type="date" name="from" value="{{ $from }}" dir="ltr"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
            </div>
            <div class="flex-1 space-y-1.5">
                <label for="to" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('إلى') }}</label>
                <input id="to" type="date" name="to" value="{{ $to }}" dir="ltr"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
            </div>
            <button type="submit"
                class="rounded-xl bg-primary px-6 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                {{ __('اعرض') }}
            </button>
        </form>

        <div class="reveal-up grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" style="--d:120ms">
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الإيراد صافي الضريبة والخصم') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold" dir="ltr">{{ number_format($summary['revenue'], 2) }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('تكلفة البضاعة المباعة') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold" dir="ltr">{{ number_format($summary['cost'], 2) }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('مجمل الربح') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold {{ $tone($summary['margin_percent']) }}" dir="ltr">
                    {{ number_format($summary['margin'], 2) }}
                </p>
                @if ($summary['margin_percent'] !== null)
                    <p class="mt-1 font-body-sm {{ $tone($summary['margin_percent']) }}" dir="ltr">{{ $summary['margin_percent'] }}%</p>
                @endif
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('بعد خصم التالف') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold" dir="ltr">{{ number_format($summary['margin_after_waste'], 2) }}</p>
                <p class="mt-1 font-body-sm text-outline" dir="ltr">{{ __('تالف') }}: {{ number_format($summary['waste'], 2) }}</p>
            </x-card>
        </div>

        <div class="reveal-up flex items-start gap-3 rounded-2xl border border-outline-variant bg-surface-container-low p-4" style="--d:160ms">
            <span class="material-symbols-outlined flex-shrink-0 text-on-surface-variant">info</span>
            <p class="min-w-0 font-body-sm text-on-surface-variant">
                {{ __('التكلفة مأخوذة من التكلفة المثبَّتة على كل حركة خروج لحظة حدوثها، لا من تكلفة الصنف اليوم — وإلا تغيّر هامش الشهر الماضي كلما تغيّر سعر مورّد.') }}
            </p>
        </div>

        <div class="reveal-up flex flex-wrap gap-2" style="--d:180ms">
            @foreach ([
                'items' => __('لكل صنف'),
                'categories' => __('لكل فئة'),
                'abc' => __('تحليل ABC'),
            ] as $key => $label)
                <a href="{{ route('margins.index', ['from' => $from, 'to' => $to, 'view' => $key]) }}"
                    class="rounded-lg px-4 py-2 font-body-sm font-bold transition-colors {{ $view === $key ? 'bg-secondary-container text-on-secondary-container' : 'text-on-surface-variant hover:bg-surface-container' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @php $rows = $view === 'categories' ? $categories : ($view === 'abc' ? $abc : $items); @endphp

            @if ($rows->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">trending_up</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد مبيعات في هذه الفترة') }}</p>
                </div>
            @elseif ($view === 'categories')
                <x-table-responsive :headers="[
                    __('الفئة'),
                    ['label' => __('أصناف'), 'align' => 'end'],
                    ['label' => __('الإيراد'), 'align' => 'end'],
                    ['label' => __('التكلفة'), 'align' => 'end'],
                    ['label' => __('الهامش'), 'align' => 'end'],
                    ['label' => __('النسبة'), 'align' => 'end'],
                ]">
                    <x-slot:table>
                        @foreach ($rows as $row)
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="px-4 py-3 font-body-sm font-bold">{{ $row['category'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg">{{ $row['items'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format($row['revenue'], 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format($row['cost'], 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold {{ $tone($row['margin_percent']) }}" dir="ltr">{{ number_format($row['margin'], 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg {{ $tone($row['margin_percent']) }}" dir="ltr">{{ $row['margin_percent'] !== null ? $row['margin_percent'].'%' : '—' }}</td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($rows as $row)
                            <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="font-body-lg font-bold">{{ $row['category'] }}</p>
                                    <span class="whitespace-nowrap font-numeral-lg font-bold {{ $tone($row['margin_percent']) }}" dir="ltr">{{ $row['margin_percent'] !== null ? $row['margin_percent'].'%' : '—' }}</span>
                                </div>
                                <p class="mt-2 font-numeral-lg font-bold" dir="ltr">{{ number_format($row['margin'], 2) }}</p>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>
            @else
                <x-table-responsive :headers="array_merge(
                    $view === 'abc' ? [__('الفئة')] : [],
                    [
                        $naming->item(),
                        ['label' => __('الكمية'), 'align' => 'end'],
                        ['label' => __('الإيراد'), 'align' => 'end'],
                        ['label' => __('التكلفة'), 'align' => 'end'],
                        ['label' => __('الهامش'), 'align' => 'end'],
                        ['label' => __('النسبة'), 'align' => 'end'],
                    ],
                    $view === 'abc' ? [['label' => __('المساهمة'), 'align' => 'end']] : [],
                )">
                    <x-slot:table>
                        @foreach ($rows as $row)
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                @if ($view === 'abc')
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span class="rounded-lg px-2 py-1 font-body-sm font-bold {{ $row['class'] === 'A' ? 'bg-success/10 text-success' : ($row['class'] === 'B' ? 'bg-secondary-container/40 text-secondary' : 'bg-surface-container text-on-surface-variant') }}">{{ $row['class'] }}</span>
                                    </td>
                                @endif
                                <td class="px-4 py-3">
                                    <a href="{{ route('margins.history', $row['item']->id) }}" class="font-body-sm font-bold hover:text-primary">{{ $row['item']->name }}</a>
                                    @if ($row['basis'] !== 'ledger')
                                        <span class="block font-body-sm text-outline">{{ $basisLabel($row['basis']) }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ rtrim(rtrim(number_format($row['quantity'], 3), '0'), '.') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format($row['revenue'], 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format($row['cost'], 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold {{ $tone($row['margin_percent']) }}" dir="ltr">{{ number_format($row['margin'], 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg {{ $tone($row['margin_percent']) }}" dir="ltr">{{ $row['margin_percent'] !== null ? $row['margin_percent'].'%' : '—' }}</td>
                                @if ($view === 'abc')
                                    <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg text-on-surface-variant" dir="ltr">{{ $row['contribution'] }}%</td>
                                @endif
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($rows as $row)
                            <a href="{{ route('margins.history', $row['item']->id) }}" class="block rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-body-lg font-bold">{{ $row['item']->name }}</p>
                                        <p class="font-body-sm text-outline" dir="ltr">{{ rtrim(rtrim(number_format($row['quantity'], 3), '0'), '.') }} × {{ number_format($row['revenue'], 2) }}</p>
                                    </div>
                                    <span class="whitespace-nowrap font-numeral-lg font-bold {{ $tone($row['margin_percent']) }}" dir="ltr">{{ $row['margin_percent'] !== null ? $row['margin_percent'].'%' : '—' }}</span>
                                </div>
                                <p class="mt-2 font-numeral-lg font-bold" dir="ltr">{{ number_format($row['margin'], 2) }}</p>
                            </a>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                @if ($view === 'abc')
                    <p class="mt-5 border-t border-outline-variant/50 pt-4 font-body-sm text-on-surface-variant">
                        {{ __('الترتيب على مساهمة الهامش لا الإيراد: صنف كثير الحركة يُباع بالتكلفة هو طريقة مزدحمة لعدم الربح، وقائمة مرتّبة بالإيراد بتحطّه بالأول.') }}
                    </p>
                @endif
            @endif
        </section>
    </div>
@endsection
