@extends('layouts.app')

@section('title', __('تتبّع اللوط - حوالاتي'))

@php
    $unit = $batch->item?->unit() ?? \App\Enums\UnitOfMeasure::Piece;
    $days = $batch->daysLeft();
    $canManage = auth('tenant')->user()?->hasPermission(\App\Enums\PermissionKey::InventoryManage);
@endphp

@section('content')
    <div class="page-enter space-y-6" x-data="{ writeOff: false, submitting: false }">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div class="min-w-0">
                <a href="{{ route('batches.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                    <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                    {{ __('اللوط والصلاحية') }}
                </a>
                <h1 class="mt-1 font-display-lg text-display-lg text-on-surface" dir="ltr">{{ $batch->batch_number }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ $batch->item?->name }}</p>
            </div>

            @if ($canManage && $onHand > 0)
                <button type="button" @click="writeOff = true"
                    class="flex items-center gap-2 rounded-xl bg-error px-5 py-2.5 font-body-sm font-bold text-on-error shadow-lg transition-all hover:opacity-90 active:scale-95">
                    <span class="material-symbols-outlined !text-xl">delete_sweep</span>
                    {{ __('إتلاف من هذا اللوط') }}
                </button>
            @endif
        </header>

        @if ($batch->isExpired() && $onHand > 0)
            <div class="reveal-up flex items-start gap-3 rounded-2xl border border-error/30 bg-error-container/10 p-4 text-error" style="--d:60ms">
                <span class="material-symbols-outlined flex-shrink-0">dangerous</span>
                <div>
                    <p class="font-body-sm font-bold">{{ __('هذا اللوط منتهي الصلاحية وما زال في المخزون') }}</p>
                    <p class="mt-1 font-body-sm">{{ __('لن يُصرف في أي بيع. أتلفه ليخرج من قيمة المخزون.') }}</p>
                </div>
            </div>
        @endif

        <div class="reveal-up grid grid-cols-1 gap-4 sm:grid-cols-4" style="--d:100ms">
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المتبقي') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold" dir="ltr">{{ number_format($onHand, $unit->decimals()) }} {{ $unit->shortLabel() }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('تاريخ الصلاحية') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold {{ $batch->isExpired() ? 'text-error' : '' }}" dir="ltr">{{ $batch->expires_at?->format('Y-m-d') ?? '—' }}</p>
                @if ($days !== null)
                    <p class="mt-1 font-body-sm text-outline">{{ $days >= 0 ? __('باقٍ :days يوم', ['days' => $days]) : __('منتهٍ منذ :days يوم', ['days' => abs($days)]) }}</p>
                @endif
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المورد') }}</p>
                <p class="mt-1 font-body-lg font-bold">{{ $batch->supplier?->name ?? '—' }}</p>
                <p class="mt-1 font-body-sm text-outline">{{ $batch->received_at?->translatedFormat('j M Y') }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('طلبات تحتوي هذا اللوط') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold">{{ $sales->count() }}</p>
            </x-card>
        </div>

        <x-card title="{{ __('من استلم هذا اللوط') }}" subtitle="{{ __('عند سحب اللوط، هؤلاء هم من يجب الاتصال بهم.') }}">
            @if ($sales->isEmpty())
                <p class="py-8 text-center font-body-sm text-on-surface-variant">{{ __('لم يُبَع شيء من هذا اللوط بعد') }}</p>
            @else
                <x-table-responsive :headers="[__('العميل'), __('الطلب'), ['label' => __('الكمية'), 'align' => 'end'], __('التاريخ'), __('')]">
                    <x-slot:table>
                        @foreach ($sales as $sale)
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="px-4 py-3">
                                    <p class="font-body-sm font-bold">{{ $sale['transfer']->customer }}</p>
                                    <p class="font-body-sm text-outline" dir="ltr">{{ $sale['transfer']->customerProfile?->phone ?? '' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm" dir="ltr">{{ mb_substr($sale['transfer']->id, -8) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold" dir="ltr">{{ number_format($sale['quantity'], $unit->decimals()) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $sale['at']?->translatedFormat('j M Y - H:i') }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('transfers.receipt', $sale['transfer']->id) }}"
                                        class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                                        aria-label="{{ __('فتح إيصال الطلب') }}">
                                        <span class="material-symbols-outlined !text-xl">receipt_long</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($sales as $sale)
                            <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-body-lg font-bold">{{ $sale['transfer']->customer }}</p>
                                        <p class="font-body-sm text-outline" dir="ltr">{{ $sale['transfer']->customerProfile?->phone ?? '' }}</p>
                                    </div>
                                    <span class="font-numeral-lg font-bold" dir="ltr">{{ number_format($sale['quantity'], $unit->decimals()) }}</span>
                                </div>
                                <p class="mt-2 font-body-sm text-on-surface-variant">{{ $sale['at']?->translatedFormat('j M Y - H:i') }}</p>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>
            @endif
        </x-card>

        <x-card title="{{ __('حركات اللوط') }}">
            @if ($movements->isEmpty())
                <p class="py-8 text-center font-body-sm text-on-surface-variant">{{ __('لا توجد حركات') }}</p>
            @else
                <x-table-responsive :headers="[__('التاريخ'), __('الحركة'), ['label' => __('الكمية'), 'align' => 'end'], __('الموقع'), __('التفاصيل')]">
                    <x-slot:table>
                        @foreach ($movements as $movement)
                            @php $qty = (float) $movement->quantity; @endphp
                            <tr class="transition-colors hover:bg-surface-container-low/50">
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
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $movement->location?->name ?? '—' }}</td>
                                <td class="px-4 py-3 font-body-sm text-on-surface-variant">
                                    {{ $movement->reason }}
                                    @if ($movement->teamMember)
                                        <span class="text-outline">— {{ $movement->teamMember->name }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($movements as $movement)
                            @php $qty = (float) $movement->quantity; @endphp
                            <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <span class="font-body-sm font-bold">{{ $movement->type->label() }}</span>
                                    <span class="font-numeral-lg font-bold {{ $qty < 0 ? 'text-error' : 'text-success' }}" dir="ltr">
                                        {{ $qty > 0 ? '+' : '' }}{{ number_format($qty, $unit->decimals()) }}
                                    </span>
                                </div>
                                <p class="mt-1 font-body-sm text-on-surface-variant">{{ $movement->created_at?->translatedFormat('j M Y - H:i') }}</p>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>
            @endif
        </x-card>

        @if ($canManage && $onHand > 0)
            <div x-show="writeOff" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="write-off-title">
                <div class="fixed inset-0 bg-black/40" x-show="writeOff" x-transition.opacity @click="writeOff = false"></div>
                <div class="relative z-10 w-full max-w-md rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                    x-show="writeOff" @keydown.escape.window="writeOff = false"
                    x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

                    <x-modal-close on-close="writeOff = false" />

                    <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
                        <h3 id="write-off-title" class="font-headline-md text-headline-md">{{ __('إتلاف من اللوط') }}</h3>
                        <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant" dir="ltr">{{ $batch->batch_number }}</p>
                    </div>

                    <form method="POST" action="{{ route('batches.write-off', $batch->id) }}" class="space-y-4 p-6" @submit="submitting = true">
                        @csrf

                        <div class="space-y-1.5">
                            <label for="write-off-quantity" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الكمية') }}</label>
                            <input id="write-off-quantity" type="number" step="{{ $unit->step() }}" min="0" max="{{ $onHand }}"
                                name="quantity" value="{{ old('quantity', $onHand) }}" required dir="ltr"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            <p class="font-body-sm text-outline" dir="ltr">{{ __('المتاح') }}: {{ number_format($onHand, $unit->decimals()) }}</p>
                            @error('quantity')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="write-off-reason" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('السبب') }}</label>
                            <input id="write-off-reason" name="reason" value="{{ old('reason', $batch->isExpired() ? __('انتهاء الصلاحية') : '') }}" required maxlength="255"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            @error('reason')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                            <button type="button" @click="writeOff = false" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                            <button type="submit" :disabled="submitting"
                                class="rounded-xl bg-error px-5 py-3 font-body-sm font-bold text-on-error shadow-lg transition-all hover:opacity-90 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                                x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('تأكيد الإتلاف'))">{{ __('تأكيد الإتلاف') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection
