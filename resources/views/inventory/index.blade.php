@extends('layouts.app')

@section('title', __('المخزون - حوالاتي'))

@php
    $canManage = auth('tenant')->user()?->hasPermission(\App\Enums\PermissionKey::InventoryManage);
    $itemLabel = $naming->item();
@endphp

@section('content')
    <div class="page-enter space-y-6"
        x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            submitting: false,
            form: {
                itemId: '{{ old('itemId', '') }}',
                type: '{{ old('type', 'adjustment') }}',
                quantity: '{{ old('quantity', '') }}',
                locationId: '{{ old('locationId', $locationId ?? '') }}',
                toLocationId: '{{ old('toLocationId', '') }}',
                reason: '{{ old('reason', '') }}',
            },
            openFor(itemId, name) { this.form = { itemId, type: 'adjustment', quantity: '', locationId: @js($locationId ?? ''), toLocationId: '', reason: '' }; this.itemName = name; this.open = true },
            itemName: '',
            get isTransfer() { return this.form.type === 'transfer' },
            get quantityLabel() { return this.form.type === 'adjustment' ? @js(__('الكمية الفعلية على الرف')) : @js(__('الكمية')) },
        }">

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('المخزون') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('الأرصدة محسوبة من دفتر الحركات — لا يوجد رصيد يُكتب يدويًا.') }}</p>
            </div>
        </header>

        <div class="reveal-up grid grid-cols-1 gap-4 sm:grid-cols-3" style="--d:60ms">
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('قيمة المخزون') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold" dir="ltr">{{ number_format($stockValue, 2) }} ₪</p>
                <p class="mt-1 font-body-sm text-outline">{{ __('بالتكلفة المتوسطة المرجّحة (IAS 2)') }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('أصناف تحت حد الطلب') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold {{ $lowStock->isNotEmpty() ? 'text-error' : '' }}">{{ $lowStock->count() }}</p>
                <p class="mt-1 font-body-sm text-outline">{{ __('بلغت نقطة إعادة الطلب أو نزلت تحتها') }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('أرصدة سالبة') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold {{ $negatives->isNotEmpty() ? 'text-error' : '' }}">{{ $negatives->count() }}</p>
                <p class="mt-1 font-body-sm text-outline">{{ __('بضاعة بيعت قبل تسجيل استلامها') }}</p>
            </x-card>
        </div>

        @if ($negatives->isNotEmpty())
            <div class="reveal-up flex items-start gap-3 rounded-2xl border border-error/30 bg-error-container/10 p-4 text-error" style="--d:100ms">
                <span class="material-symbols-outlined flex-shrink-0">error</span>
                <div class="min-w-0">
                    <p class="font-body-sm font-bold">{{ __('أصناف رصيدها سالب') }}</p>
                    <p class="mt-1 font-body-sm">{{ __('كل تقييم مبني على هذه الأصناف غير صحيح حتى تُسجَّل مشترياتها الناقصة.') }}</p>
                    <p class="mt-2 font-body-sm">
                        @foreach ($negatives->take(8) as $row)
                            <span class="inline-block rounded-lg bg-error/10 px-2 py-1">{{ $row->item?->name }} ({{ number_format((float) $row->qty, 3) }})</span>
                        @endforeach
                    </p>
                </div>
            </div>
        @endif

        <form method="GET" action="{{ route('inventory.index') }}" class="reveal-up flex flex-col gap-3 sm:flex-row" style="--d:140ms">
            <x-keep-per-page />
            <div class="relative flex-1">
                <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">search</span>
                <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('ابحث بالاسم أو الباركود أو الرمز...') }}" aria-label="{{ __('بحث') }}"
                    class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
            </div>
            @if ($locations->count() > 1)
                <select name="locationId" onchange="this.form.submit()" aria-label="{{ __('الموقع') }}"
                    class="rounded-2xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                    <option value="">{{ __('كل المواقع') }}</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected($locationId === $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            @endif
        </form>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($items->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">inventory_2</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد أصناف تخضع لتتبّع المخزون بعد') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[
                    $itemLabel,
                    __('الوحدة'),
                    ['label' => __('الرصيد'), 'align' => 'end'],
                    ['label' => __('التكلفة'), 'align' => 'end'],
                    ['label' => __('القيمة'), 'align' => 'end'],
                    ['label' => __('الهامش'), 'align' => 'end'],
                    __('إجراءات'),
                ]">
                    <x-slot:table>
                        @foreach ($items as $item)
                            @php
                                $qty = $onHand[$item->id] ?? 0.0;
                                $unit = $item->unit();
                                $low = $item->reorder_point !== null && $qty <= (float) $item->reorder_point;
                                $margin = $item->marginPercent();
                            @endphp
                            <tr class="reveal-up transition-colors hover:bg-surface-container-low/50" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                            <span class="material-symbols-outlined !text-xl">{{ $item->icon ?: 'inventory_2' }}</span>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-body-sm font-bold">{{ $item->name }}</p>
                                            <p class="font-body-sm text-outline" dir="ltr">{{ $item->barcode ?: $item->sku ?: $item->menuCategory?->name }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $unit->shortLabel() }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold {{ $qty < 0 ? 'text-error' : ($low ? 'text-secondary' : '') }}" dir="ltr">
                                    {{ number_format($qty, $unit->decimals()) }}
                                    @if ($low)
                                        <span class="material-symbols-outlined !text-base align-middle" title="{{ __('تحت حد الطلب') }}">warning</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format((float) $item->cost, 3) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format($qty * (float) $item->cost, 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg {{ $margin !== null && $margin < 0 ? 'text-error' : '' }}" dir="ltr">
                                    {{ $margin === null ? '—' : $margin.'%' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('inventory.show', $item->id) }}"
                                            class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                                            title="{{ __('سجل الحركات') }}" aria-label="{{ __('سجل حركات :item', ['item' => $item->name]) }}">
                                            <span class="material-symbols-outlined !text-xl">history</span>
                                        </a>
                                        @if ($canManage)
                                            <button type="button" @click="openFor(@js($item->id), @js($item->name))"
                                                class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                                                title="{{ __('حركة مخزنية') }}" aria-label="{{ __('حركة مخزنية على :item', ['item' => $item->name]) }}">
                                                <span class="material-symbols-outlined !text-xl">tune</span>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($items as $item)
                            @php
                                $qty = $onHand[$item->id] ?? 0.0;
                                $unit = $item->unit();
                                $low = $item->reorder_point !== null && $qty <= (float) $item->reorder_point;
                            @endphp
                            <div class="reveal-up rounded-2xl border border-outline-variant bg-surface-container-lowest p-4" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-body-lg font-bold">{{ $item->name }}</p>
                                        <p class="font-body-sm text-outline" dir="ltr">{{ $item->barcode ?: $item->sku ?: $item->menuCategory?->name }}</p>
                                    </div>
                                    <p class="whitespace-nowrap font-numeral-lg font-bold {{ $qty < 0 ? 'text-error' : ($low ? 'text-secondary' : '') }}" dir="ltr">
                                        {{ number_format($qty, $unit->decimals()) }} {{ $unit->shortLabel() }}
                                    </p>
                                </div>
                                <div class="mt-3 flex items-center justify-between gap-3 border-t border-outline-variant/50 pt-3">
                                    <span class="font-body-sm text-on-surface-variant" dir="ltr">{{ __('التكلفة') }}: {{ number_format((float) $item->cost, 3) }}</span>
                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('inventory.show', $item->id) }}" class="rounded-lg p-2 text-on-surface-variant hover:text-primary" aria-label="{{ __('سجل حركات :item', ['item' => $item->name]) }}">
                                            <span class="material-symbols-outlined !text-xl">history</span>
                                        </a>
                                        @if ($canManage)
                                            <button type="button" @click="openFor(@js($item->id), @js($item->name))" class="rounded-lg p-2 text-on-surface-variant hover:text-primary" aria-label="{{ __('حركة مخزنية على :item', ['item' => $item->name]) }}">
                                                <span class="material-symbols-outlined !text-xl">tune</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$items" :perPage="$perPage" />
            @endif
        </section>

        @if ($canManage)
            @include('inventory._movement-modal', ['locations' => $locations])
        @endif
    </div>
@endsection
