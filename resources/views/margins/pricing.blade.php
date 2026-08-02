@extends('layouts.app')

@section('title', __('التسعير - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6">
        <header>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('التسعير') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('الأصناف التي هامشها رقيق أو ذهب أو تآكل بهدوء تحت سعر ما عاد حدا راجعه.') }}</p>
        </header>

        @include('margins._tabs', ['active' => 'pricing'])

        <form method="GET" action="{{ route('margins.pricing') }}" class="reveal-up flex flex-col gap-3 sm:flex-row sm:items-end" style="--d:80ms">
            <div class="flex-1 space-y-1.5">
                <label for="target" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الهامش المستهدف %') }}</label>
                <input id="target" type="number" name="target" min="0" max="95" step="1" value="{{ $target }}" dir="ltr"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                <p class="font-body-sm text-outline">{{ __('يُعرض هنا كل صنف تحت هذا الهامش، مع السعر الذي يعيده إليه.') }}</p>
            </div>
            <button type="submit"
                class="rounded-xl bg-primary px-6 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                {{ __('اعرض') }}
            </button>
        </form>

        @php
            $belowCost = $rows->where('below_cost', true);
            $eroded = $rows->filter(fn ($row) => $row['eroded_by'] !== null && $row['eroded_by'] >= 5);
        @endphp

        <div class="reveal-up grid grid-cols-1 gap-4 sm:grid-cols-3" style="--d:120ms">
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('تحت الهامش المستهدف') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold">{{ $rows->count() }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('يُباع بأقل من تكلفته') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold {{ $belowCost->isNotEmpty() ? 'text-error' : '' }}">{{ $belowCost->count() }}</p>
                <p class="mt-1 font-body-sm text-outline">{{ __('كل بيعة منها تزيد الخسارة') }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('هامش تآكل ٥ نقاط أو أكثر') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold {{ $eroded->isNotEmpty() ? 'text-tertiary' : '' }}">{{ $eroded->count() }}</p>
                <p class="mt-1 font-body-sm text-outline">{{ __('منذ آخر مرة عُدّل فيها السعر') }}</p>
            </x-card>
        </div>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($rows->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-success/50">verified</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('كل صنف له تكلفة مسجّلة فوق الهامش المستهدف') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[
                    $naming->item(),
                    ['label' => __('السعر'), 'align' => 'end'],
                    ['label' => __('التكلفة'), 'align' => 'end'],
                    ['label' => __('الهامش'), 'align' => 'end'],
                    ['label' => __('التآكل'), 'align' => 'end'],
                    ['label' => __('السعر المقترح'), 'align' => 'end'],
                ]">
                    <x-slot:table>
                        @foreach ($rows as $row)
                            <tr class="transition-colors hover:bg-surface-container-low/50 {{ $row['below_cost'] ? 'bg-error-container/5' : '' }}">
                                <td class="px-4 py-3">
                                    <a href="{{ route('margins.history', $row['item']->id) }}" class="font-body-sm font-bold hover:text-primary">{{ $row['item']->name }}</a>
                                    <span class="block font-body-sm text-outline">{{ $row['category'] ?? __('بلا فئة') }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format($row['price'], 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format($row['cost'], 3) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold {{ $row['below_cost'] ? 'text-error' : 'text-tertiary' }}" dir="ltr">
                                    {{ $row['margin_percent'] !== null ? $row['margin_percent'].'%' : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">
                                    @if ($row['eroded_by'] !== null && $row['eroded_by'] > 0)
                                        <span class="text-tertiary">−{{ $row['eroded_by'] }}</span>
                                        <span class="block font-body-sm text-outline">{{ $row['priced_at']?->translatedFormat('j M Y') }}</span>
                                    @else
                                        <span class="text-outline">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold text-primary" dir="ltr">{{ number_format($row['suggested_price'], 2) }}</td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($rows as $row)
                            <a href="{{ route('margins.history', $row['item']->id) }}" class="block rounded-2xl border p-4 {{ $row['below_cost'] ? 'border-error/30 bg-error-container/5' : 'border-outline-variant bg-surface-container-lowest' }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-body-lg font-bold">{{ $row['item']->name }}</p>
                                        <p class="font-body-sm text-outline" dir="ltr">{{ number_format($row['price'], 2) }} ← {{ number_format($row['cost'], 3) }}</p>
                                    </div>
                                    <span class="whitespace-nowrap font-numeral-lg font-bold {{ $row['below_cost'] ? 'text-error' : 'text-tertiary' }}" dir="ltr">
                                        {{ $row['margin_percent'] !== null ? $row['margin_percent'].'%' : '—' }}
                                    </span>
                                </div>
                                <p class="mt-2 font-body-sm text-primary" dir="ltr">{{ __('المقترح') }}: {{ number_format($row['suggested_price'], 2) }}</p>
                            </a>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <p class="mt-5 border-t border-outline-variant/50 pt-4 font-body-sm text-on-surface-variant">
                    {{ __('«التآكل» يقارن الهامش اليوم بالهامش يوم ضُبط السعر — لا يظهر إلا للأصناف التي تغيّر سعرها بعد تشغيل هذه الميزة، لأن ما لم يُسجَّل لا يُستعاد.') }}
                </p>
            @endif
        </section>
    </div>
@endsection
