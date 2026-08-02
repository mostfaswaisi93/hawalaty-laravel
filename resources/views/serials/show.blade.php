@extends('layouts.app')

@section('title', $unit->serial.' - '.__('تتبّع الوحدة'))

@php $daysLeft = $unit->warrantyDaysLeft(); @endphp

@section('content')
    <div class="page-enter space-y-6" x-data="{ scrapping: false, submitting: false }">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div class="min-w-0">
                <a href="{{ route('serials.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                    <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                    {{ __('الأرقام التسلسلية') }}
                </a>
                <h1 class="mt-1 font-display-lg text-display-lg text-on-surface" dir="ltr">{{ $unit->serial }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ $unit->item?->name }}</p>
            </div>

            <div class="flex items-center gap-2">
                @if ($unit->status === \App\Enums\SerialStatus::Returned)
                    <form method="POST" action="{{ route('serials.update', $unit->id) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="action" value="restock" />
                        <button type="submit" class="rounded-xl bg-surface-container px-5 py-2.5 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">{{ __('إعادة للمخزون') }}</button>
                    </form>
                @endif

                @if ($unit->status->isAvailable())
                    <button type="button" @click="scrapping = true"
                        class="flex items-center gap-2 rounded-xl bg-error px-5 py-2.5 font-body-sm font-bold text-on-error shadow-lg transition-all hover:opacity-90 active:scale-95">
                        <span class="material-symbols-outlined !text-xl">delete_forever</span>
                        {{ __('إتلاف الوحدة') }}
                    </button>
                @endif
            </div>
        </header>

        <div class="reveal-up grid grid-cols-1 gap-4 sm:grid-cols-4" style="--d:60ms">
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الحالة') }}</p>
                <p class="mt-1 flex items-center gap-1.5 font-body-lg font-bold">
                    <span class="material-symbols-outlined !text-xl text-primary">{{ $unit->status->icon() }}</span>
                    {{ $unit->status->label() }}
                </p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الضمان') }}</p>
                <p class="mt-1 font-body-lg font-bold {{ $unit->underWarranty() ? 'text-success' : ($unit->hasWarranty() ? 'text-error' : '') }}">
                    {{ $unit->warrantyLabel() }}
                </p>
                @if ($daysLeft !== null)
                    <p class="mt-1 font-body-sm text-outline">
                        {{ $daysLeft >= 0 ? __('باقٍ :days يوم', ['days' => $daysLeft]) : __('انتهى منذ :days يوم', ['days' => abs($daysLeft)]) }}
                    </p>
                @endif
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('من المورد') }}</p>
                <p class="mt-1 font-body-lg font-bold">{{ $unit->purchase?->supplier?->name ?? '—' }}</p>
                <p class="mt-1 font-body-sm text-outline">{{ $unit->received_at?->translatedFormat('j M Y') }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('إلى العميل') }}</p>
                <p class="mt-1 font-body-lg font-bold">{{ $unit->customer?->name ?? $unit->transfer?->customer ?? '—' }}</p>
                <p class="mt-1 font-body-sm text-outline">{{ $unit->sold_at?->translatedFormat('j M Y') }}</p>
            </x-card>
        </div>

        <x-card title="{{ __('بيانات الوحدة') }}">
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-3">
                <div>
                    <dt class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('التكلفة') }}</dt>
                    <dd class="mt-1 font-numeral-lg font-bold" dir="ltr">{{ number_format((float) $unit->unit_cost, 3) }}</dd>
                </div>
                <div>
                    <dt class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الموقع') }}</dt>
                    <dd class="mt-1 font-body-lg">{{ $unit->location?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اللوط') }}</dt>
                    <dd class="mt-1 font-body-lg" dir="ltr">{{ $unit->batch?->batch_number ?? '—' }}</dd>
                </div>
                @if ($unit->warranty_starts_at)
                    <div>
                        <dt class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('بداية الضمان') }}</dt>
                        <dd class="mt-1 font-numeral-lg" dir="ltr">{{ $unit->warranty_starts_at->format('Y-m-d') }}</dd>
                    </div>
                    <div>
                        <dt class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('نهاية الضمان') }}</dt>
                        <dd class="mt-1 font-numeral-lg" dir="ltr">{{ $unit->warranty_ends_at?->format('Y-m-d') }}</dd>
                    </div>
                    <div>
                        <dt class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('مدة الضمان') }}</dt>
                        <dd class="mt-1 font-body-lg">{{ __(':count شهر', ['count' => $unit->warranty_months]) }}</dd>
                    </div>
                @endif
            </dl>

            @if ($unit->notes)
                <p class="mt-4 rounded-xl bg-surface-container-low p-3 font-body-sm text-on-surface-variant">{{ $unit->notes }}</p>
            @endif
        </x-card>

        <x-card title="{{ __('مطالبات الضمان') }}">
            @if ($unit->claims->isEmpty())
                <p class="py-6 text-center font-body-sm text-on-surface-variant">{{ __('لا توجد مطالبات على هذه الوحدة') }}</p>
            @else
                <ul class="divide-y divide-outline-variant/60 rounded-2xl border border-outline-variant">
                    @foreach ($unit->claims as $claim)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <a href="{{ route('warranty.show', $claim->id) }}" class="font-body-lg font-bold transition-colors hover:text-primary">{{ $claim->issue }}</a>
                                <p class="font-body-sm text-outline">{{ $claim->reported_at?->translatedFormat('j M Y') }}</p>
                            </div>
                            <span class="whitespace-nowrap rounded-lg px-2 py-1 font-body-sm font-bold {{ $claim->status->tone() }}">{{ $claim->status->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card title="{{ __('حركات الوحدة') }}">
            @if ($unit->movements->isEmpty())
                <p class="py-6 text-center font-body-sm text-on-surface-variant">{{ __('لا توجد حركات') }}</p>
            @else
                <ul class="divide-y divide-outline-variant/60 rounded-2xl border border-outline-variant">
                    @foreach ($unit->movements as $movement)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 font-body-sm font-bold">
                                <span class="material-symbols-outlined !text-lg text-on-surface-variant">{{ $movement->type->icon() }}</span>
                                {{ $movement->type->label() }}
                            </span>
                            <span class="font-body-sm text-on-surface-variant">
                                {{ $movement->created_at?->translatedFormat('j M Y - H:i') }}
                                @if ($movement->teamMember)
                                    — {{ $movement->teamMember->name }}
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        @if ($unit->status->isAvailable())
            <div x-show="scrapping" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="scrap-title">
                <div class="fixed inset-0 bg-black/40" x-show="scrapping" x-transition.opacity @click="scrapping = false"></div>
                <div class="relative z-10 w-full max-w-md rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                    x-show="scrapping" @keydown.escape.window="scrapping = false"
                    x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

                    <x-modal-close on-close="scrapping = false" />

                    <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
                        <h3 id="scrap-title" class="font-headline-md text-headline-md">{{ __('إتلاف الوحدة') }}</h3>
                        <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant" dir="ltr">{{ $unit->serial }}</p>
                    </div>

                    <form method="POST" action="{{ route('serials.update', $unit->id) }}" class="space-y-4 p-6" @submit="submitting = true">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="action" value="scrap" />

                        <div class="space-y-1.5">
                            <label for="scrap-reason" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('السبب') }}</label>
                            <input id="scrap-reason" name="reason" required maxlength="255" placeholder="{{ __('مثال: تلف أثناء العرض') }}"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            @error('reason')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                            <button type="button" @click="scrapping = false" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
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
