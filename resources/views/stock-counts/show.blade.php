@extends('layouts.app')

@section('title', __('جلسة جرد - حوالاتي'))

@php
    $countedLines = $count->lines->whereNotNull('counted_quantity');
    $varianceValue = $countedLines->sum(fn ($line) => ($line->variance() ?? 0) * (float) $line->unit_cost);
@endphp

@section('content')
    <div class="page-enter space-y-6">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div class="min-w-0">
                <a href="{{ route('stock-counts.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                    <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                    {{ __('الجرد') }}
                </a>
                <h1 class="mt-1 font-display-lg text-display-lg text-on-surface" dir="ltr">{{ $count->reference ?: mb_substr($count->id, -8) }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ $count->location?->name }} — {{ $count->statusLabel() }}</p>
            </div>

            @if ($count->isOpen())
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('stock-counts.cancel', $count->id) }}"
                        @submit.prevent="if (confirm('{{ __('إلغاء جلسة الجرد؟') }}')) $el.submit()">
                        @csrf
                        <button type="submit" class="rounded-xl bg-surface-container px-5 py-2.5 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">{{ __('إلغاء الجلسة') }}</button>
                    </form>
                    <form method="POST" action="{{ route('stock-counts.post', $count->id) }}"
                        @submit.prevent="if (confirm('{{ __('ترحيل الفروقات إلى المخزون؟ ستُسجَّل حركات تسوية ولا يمكن التراجع.') }}')) $el.submit()">
                        @csrf
                        <button type="submit" class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                            <span class="material-symbols-outlined !text-xl">task_alt</span>
                            {{ __('ترحيل الفروقات') }}
                        </button>
                    </form>
                </div>
            @endif
        </header>

        <div class="reveal-up grid grid-cols-1 gap-4 sm:grid-cols-3" style="--d:60ms">
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('أسطر مجرودة') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold">{{ $countedLines->count() }} / {{ $count->lines->count() }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('أسطر بها فرق') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold">{{ $countedLines->filter(fn ($l) => abs($l->variance() ?? 0) > 0.0005)->count() }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('قيمة الفرق') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold {{ $varianceValue < 0 ? 'text-error' : '' }}" dir="ltr">{{ number_format($varianceValue, 2) }} ₪</p>
            </x-card>
        </div>

        <form method="POST" action="{{ route('stock-counts.update', $count->id) }}" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            @method('PUT')

            <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                <x-table-responsive :headers="[
                    __('الصنف'),
                    ['label' => __('الدفتري'), 'align' => 'end'],
                    ['label' => __('المعدود'), 'align' => 'end'],
                    ['label' => __('الفرق'), 'align' => 'end'],
                    ['label' => __('قيمة الفرق'), 'align' => 'end'],
                ]">
                    <x-slot:table>
                        @foreach ($count->lines as $line)
                            @php
                                $unit = $line->item?->unit() ?? \App\Enums\UnitOfMeasure::Piece;
                                $variance = $line->variance();
                            @endphp
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="px-4 py-3 font-body-sm font-bold">{{ $line->item?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg text-on-surface-variant" dir="ltr">{{ number_format((float) $line->expected_quantity, $unit->decimals()) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end">
                                    @if ($count->isOpen())
                                        <input type="number" step="{{ $unit->step() }}" min="0"
                                            name="counted[{{ $line->id }}]" value="{{ $line->counted_quantity !== null ? (float) $line->counted_quantity : '' }}"
                                            dir="ltr" aria-label="{{ __('الكمية المعدودة لـ :item', ['item' => $line->item?->name]) }}"
                                            class="w-28 rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-end font-numeral-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                                    @else
                                        <span class="font-numeral-lg" dir="ltr">{{ $line->counted_quantity !== null ? number_format((float) $line->counted_quantity, $unit->decimals()) : '—' }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold {{ $variance !== null && $variance < 0 ? 'text-error' : ($variance ? 'text-success' : '') }}" dir="ltr">
                                    {{ $variance === null ? '—' : ($variance > 0 ? '+' : '').number_format($variance, $unit->decimals()) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">
                                    {{ $variance === null ? '—' : number_format($variance * (float) $line->unit_cost, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($count->lines as $line)
                            @php $unit = $line->item?->unit() ?? \App\Enums\UnitOfMeasure::Piece; @endphp
                            <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <p class="font-body-lg font-bold">{{ $line->item?->name ?? '—' }}</p>
                                <p class="mt-1 font-body-sm text-on-surface-variant" dir="ltr">{{ __('الدفتري') }}: {{ number_format((float) $line->expected_quantity, $unit->decimals()) }}</p>
                                @if ($count->isOpen())
                                    <input type="number" step="{{ $unit->step() }}" min="0"
                                        name="counted[{{ $line->id }}]" value="{{ $line->counted_quantity !== null ? (float) $line->counted_quantity : '' }}"
                                        dir="ltr" placeholder="{{ __('المعدود') }}" aria-label="{{ __('الكمية المعدودة لـ :item', ['item' => $line->item?->name]) }}"
                                        class="mt-2 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 font-numeral-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                                @endif
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                @if ($count->isOpen())
                    <div class="mt-6 flex justify-end border-t border-outline-variant/50 pt-5">
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-surface-container px-5 py-3 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high disabled:cursor-not-allowed disabled:opacity-60"
                            x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ الكميات'))">{{ __('حفظ الكميات') }}</button>
                    </div>
                @endif
            </section>
        </form>
    </div>
@endsection
