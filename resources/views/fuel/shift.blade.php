@extends('layouts.app')

@section('title', __('وردية محروقات - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6">
        <header>
            <a href="{{ route('fuel.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                {{ __('الورديات والخزانات') }}
            </a>
            <h1 class="mt-1 font-display-lg text-display-lg text-on-surface">
                {{ $shift->name ?: $shift->shift_date?->translatedFormat('j F Y') }}
            </h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">
                {{ $shift->statusLabel() }}
                @if ($shift->teamMember)
                    — {{ $shift->teamMember->name }}
                @endif
            </p>
        </header>

        <x-card title="{{ __('قراءات المضخات') }}">
            <x-table-responsive :headers="[
                __('المضخة'),
                ['label' => __('الفتح'), 'align' => 'end'],
                ['label' => __('الإقفال'), 'align' => 'end'],
                ['label' => __('اختبار'), 'align' => 'end'],
                ['label' => __('المُباع'), 'align' => 'end'],
                ['label' => __('القيمة'), 'align' => 'end'],
            ]">
                <x-slot:table>
                    @foreach ($shift->readings as $reading)
                        <tr class="transition-colors hover:bg-surface-container-low/50">
                            <td class="px-4 py-3">
                                <p class="font-body-sm font-bold">{{ $reading->pump?->name }}</p>
                                <p class="font-body-sm text-outline">{{ $reading->pump?->tank?->item?->name }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format((float) $reading->opening_reading, 3) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ $reading->closing_reading === null ? '—' : number_format((float) $reading->closing_reading, 3) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg text-on-surface-variant" dir="ltr">{{ number_format((float) $reading->test_litres, 3) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold" dir="ltr">{{ number_format($reading->litresSold(), 3) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format($reading->value(), 2) }}</td>
                        </tr>
                    @endforeach
                </x-slot:table>

                <x-slot:cards>
                    @foreach ($shift->readings as $reading)
                        <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                            <p class="font-body-lg font-bold">{{ $reading->pump?->name }}</p>
                            <p class="mt-1 font-numeral-lg" dir="ltr">{{ number_format($reading->litresSold(), 3) }} {{ __('لتر') }}</p>
                            <p class="font-body-sm text-outline" dir="ltr">{{ number_format($reading->value(), 2) }} ₪</p>
                        </div>
                    @endforeach
                </x-slot:cards>
            </x-table-responsive>

            <p class="mt-4 flex justify-between border-t border-outline-variant/50 pt-4 font-headline-md text-headline-md font-bold">
                <span>{{ __('إجمالي المبيعات') }}</span>
                <span class="font-numeral-lg" dir="ltr">{{ number_format($shift->readings->sum(fn ($r) => $r->value()), 2) }} ₪</span>
            </p>
        </x-card>

        @include('fuel._reconciliation', ['rows' => $reconciliation, 'service' => $service])

        @if ($shift->closed_at)
            <p class="font-body-sm text-outline">{{ __('أُقفلت في :date', ['date' => $shift->closed_at->translatedFormat('j F Y - H:i')]) }}</p>
        @endif
    </div>
@endsection
