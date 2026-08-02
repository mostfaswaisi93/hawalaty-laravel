@extends('layouts.app')

@section('title', $item->name.' - '.__('سجل المخزون'))

@php $unit = $item->unit(); @endphp

@section('content')
    <div class="page-enter space-y-6">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div class="min-w-0">
                <a href="{{ route('inventory.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                    <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                    {{ __('المخزون') }}
                </a>
                <h1 class="mt-1 font-display-lg text-display-lg text-on-surface">{{ $item->name }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant" dir="ltr">{{ $item->barcode ?: $item->sku ?: '' }}</p>
            </div>
        </header>

        <div class="reveal-up grid grid-cols-1 gap-4 sm:grid-cols-3" style="--d:60ms">
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الرصيد الحالي') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold {{ $onHand < 0 ? 'text-error' : '' }}" dir="ltr">
                    {{ number_format($onHand, $unit->decimals()) }} {{ $unit->shortLabel() }}
                </p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('التكلفة المتوسطة') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold" dir="ltr">{{ number_format((float) $item->cost, 3) }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('قيمة الرصيد') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold" dir="ltr">{{ number_format($onHand * (float) $item->cost, 2) }} ₪</p>
            </x-card>
        </div>

        @if ($locations->count() > 1)
            <form method="GET" action="{{ route('inventory.show', $item->id) }}" class="reveal-up max-w-xs" style="--d:100ms">
                <x-keep-per-page />
                <select name="locationId" onchange="this.form.submit()" aria-label="{{ __('الموقع') }}"
                    class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                    <option value="">{{ __('كل المواقع') }}</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected($locationId === $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($movements->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">history</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد حركات على هذا الصنف بعد') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[
                    __('التاريخ'),
                    __('الحركة'),
                    ['label' => __('الكمية'), 'align' => 'end'],
                    ['label' => __('التكلفة'), 'align' => 'end'],
                    __('الموقع'),
                    __('التفاصيل'),
                ]">
                    <x-slot:table>
                        @foreach ($movements as $movement)
                            @php $qty = (float) $movement->quantity; @endphp
                            <tr class="reveal-up transition-colors hover:bg-surface-container-low/50" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $movement->created_at?->translatedFormat('j M Y - H:i') }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 font-body-sm font-bold">
                                        <span class="material-symbols-outlined !text-lg text-on-surface-variant">{{ $movement->type->icon() }}</span>
                                        {{ $movement->type->label() }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold {{ $qty < 0 ? 'text-error' : 'text-success' }}" dir="ltr">
                                    {{ $qty > 0 ? '+' : '' }}{{ number_format($qty, $unit->decimals()) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format((float) $movement->unit_cost, 3) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $movement->location?->name ?? '—' }}</td>
                                <td class="px-4 py-3 font-body-sm text-on-surface-variant">
                                    {{ $movement->reason }}
                                    @if ($movement->batch_number)
                                        <span class="ms-1 inline-block rounded-lg bg-surface-container px-2 py-0.5" dir="ltr">{{ $movement->batch_number }}</span>
                                    @endif
                                    @if ($movement->expires_at)
                                        <span class="ms-1 inline-block rounded-lg bg-secondary-container/30 px-2 py-0.5" dir="ltr">{{ $movement->expires_at->format('Y-m-d') }}</span>
                                    @endif
                                    @if ($movement->teamMember)
                                        <span class="ms-1 text-outline">— {{ $movement->teamMember->name }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($movements as $movement)
                            @php $qty = (float) $movement->quantity; @endphp
                            <div class="reveal-up rounded-2xl border border-outline-variant bg-surface-container-lowest p-4" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <div class="flex items-start justify-between gap-3">
                                    <span class="inline-flex items-center gap-1.5 font-body-sm font-bold">
                                        <span class="material-symbols-outlined !text-lg text-on-surface-variant">{{ $movement->type->icon() }}</span>
                                        {{ $movement->type->label() }}
                                    </span>
                                    <span class="font-numeral-lg font-bold {{ $qty < 0 ? 'text-error' : 'text-success' }}" dir="ltr">
                                        {{ $qty > 0 ? '+' : '' }}{{ number_format($qty, $unit->decimals()) }}
                                    </span>
                                </div>
                                <p class="mt-2 font-body-sm text-on-surface-variant">{{ $movement->created_at?->translatedFormat('j M Y - H:i') }} — {{ $movement->location?->name }}</p>
                                @if ($movement->reason)
                                    <p class="mt-1 font-body-sm text-on-surface-variant">{{ $movement->reason }}</p>
                                @endif
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$movements" :perPage="$perPage" />
            @endif
        </section>
    </div>
@endsection
