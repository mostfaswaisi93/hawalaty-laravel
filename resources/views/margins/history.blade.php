@extends('layouts.app')

@section('title', __('سجل السعر - حوالاتي'))

@section('content')
    <div class="page-enter mx-auto max-w-4xl space-y-6">
        <header>
            <a href="{{ route('margins.pricing') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                {{ __('التسعير') }}
            </a>
            <h1 class="mt-1 font-display-lg text-display-lg text-on-surface">{{ $item->name }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('سجل السعر، وتكلفة الصنف عند كل تغيير.') }}</p>
        </header>

        <div class="reveal-up grid grid-cols-1 gap-4 sm:grid-cols-3" style="--d:60ms">
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('السعر الحالي') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold" dir="ltr">{{ number_format((float) $item->price, 2) }}</p>
                <p class="mt-1 font-body-sm text-outline" dir="ltr">{{ __('صافي الضريبة') }}: {{ number_format($item->netPrice(), 3) }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('التكلفة الحالية') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold" dir="ltr">{{ number_format((float) $item->cost, 3) }}</p>
                <p class="mt-1 font-body-sm text-outline">{{ __('المتوسط المرجّح') }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الهامش') }}</p>
                @php $margin = $item->marginPercent(); @endphp
                <p class="mt-1 font-numeral-lg text-headline-md font-bold {{ $margin === null ? 'text-outline' : ($margin < 0 ? 'text-error' : ($margin < 15 ? 'text-tertiary' : 'text-success')) }}" dir="ltr">
                    {{ $margin !== null ? $margin.'%' : '—' }}
                </p>
            </x-card>
        </div>

        <x-card title="{{ __('التغييرات') }}">
            @if ($changes->isEmpty())
                <div class="flex flex-col items-center gap-3 py-12 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">history</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا يوجد سجل لهذا الصنف') }}</p>
                    <p class="max-w-md font-body-sm text-outline">{{ __('التسجيل يبدأ من لحظة تفعيل هذه الميزة — ما لم يُكتَب لا يُستعاد، وملء الصفوف القديمة بتكلفة اليوم كان سيعطي تقريرًا يبدو مفيدًا وهو ليس كذلك.') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[
                    __('التاريخ'),
                    ['label' => __('من'), 'align' => 'end'],
                    ['label' => __('إلى'), 'align' => 'end'],
                    ['label' => __('التغيير'), 'align' => 'end'],
                    ['label' => __('التكلفة حينها'), 'align' => 'end'],
                    ['label' => __('الهامش حينها'), 'align' => 'end'],
                    __('بواسطة'),
                ]">
                    <x-slot:table>
                        @foreach ($changes as $change)
                            @php
                                $rate = (float) $item->tax_rate;
                                $net = $item->tax_included && $rate > 0
                                    ? (float) $change->new_price / (1 + $rate / 100)
                                    : (float) $change->new_price;
                                $costThen = (float) $change->cost_at_change;
                                $marginThen = $net > 0 && $costThen > 0 ? round((($net - $costThen) / $net) * 100, 1) : null;
                            @endphp
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm">{{ $change->created_at->translatedFormat('j M Y - H:i') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg text-on-surface-variant" dir="ltr">
                                    {{ $change->old_price !== null ? number_format((float) $change->old_price, 2) : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold" dir="ltr">{{ number_format((float) $change->new_price, 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg {{ $change->changePercent() === null ? 'text-outline' : ($change->isIncrease() ? 'text-success' : 'text-error') }}" dir="ltr">
                                    {{ $change->changePercent() !== null ? ($change->isIncrease() ? '+' : '').$change->changePercent().'%' : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg text-on-surface-variant" dir="ltr">{{ number_format($costThen, 3) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ $marginThen !== null ? $marginThen.'%' : '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $change->teamMember?->name ?? __('النظام') }}</td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($changes as $change)
                            <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-body-lg font-bold" dir="ltr">
                                            {{ $change->old_price !== null ? number_format((float) $change->old_price, 2).' ← ' : '' }}{{ number_format((float) $change->new_price, 2) }}
                                        </p>
                                        <p class="font-body-sm text-outline">{{ $change->created_at->translatedFormat('j M Y') }}</p>
                                    </div>
                                    <span class="whitespace-nowrap font-numeral-lg font-bold {{ $change->changePercent() === null ? 'text-outline' : ($change->isIncrease() ? 'text-success' : 'text-error') }}" dir="ltr">
                                        {{ $change->changePercent() !== null ? ($change->isIncrease() ? '+' : '').$change->changePercent().'%' : '—' }}
                                    </span>
                                </div>
                                <p class="mt-2 font-body-sm text-outline" dir="ltr">{{ __('التكلفة حينها') }}: {{ number_format((float) $change->cost_at_change, 3) }}</p>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>
            @endif
        </x-card>
    </div>
@endsection
