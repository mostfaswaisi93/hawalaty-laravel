@extends('layouts.app')

@section('title', __('المواعيد - حوالاتي'))

@php $today = \Illuminate\Support\Carbon::parse($date); @endphp

@section('content')
    <div class="page-enter space-y-6" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }}, submitting: false }">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('المواعيد') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('في هذا النشاط لا يوجد مخزون ينفد — الوقت هو المورد النادر.') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('scheduling.resources') }}"
                    class="flex items-center gap-2 rounded-xl bg-surface-container px-5 py-2.5 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                    <span class="material-symbols-outlined !text-xl">groups</span>
                    {{ __('الموارد') }}
                </a>
                <button type="button" @click="open = true" @disabled($resources->isEmpty())
                    class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50">
                    <span class="material-symbols-outlined !text-xl">add</span>
                    {{ __('حجز موعد') }}
                </button>
            </div>
        </header>

        @if ($resources->isEmpty())
            <div class="flex items-start gap-3 rounded-2xl border border-secondary-container/40 bg-secondary-container/10 p-4 text-secondary">
                <span class="material-symbols-outlined flex-shrink-0">info</span>
                <p class="font-body-sm">
                    {{ __('أضف فنيًا أو مكان عمل أولًا.') }}
                    <a href="{{ route('scheduling.resources') }}" class="font-bold underline">{{ __('الموارد') }}</a>
                </p>
            </div>
        @endif

        <form method="GET" action="{{ route('scheduling.index') }}" class="reveal-up flex flex-wrap items-center gap-3" style="--d:60ms">
            <a href="{{ route('scheduling.index', ['date' => $today->copy()->subDay()->toDateString()]) }}"
                class="flex h-11 w-11 items-center justify-center rounded-xl bg-surface-container text-on-surface transition-colors hover:bg-surface-container-high"
                aria-label="{{ __('اليوم السابق') }}">
                <span class="material-symbols-outlined rtl:-scale-x-100">chevron_left</span>
            </a>

            <label for="scheduling-date" class="sr-only">{{ __('التاريخ') }}</label>
            <input id="scheduling-date" type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" dir="ltr"
                class="rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />

            <a href="{{ route('scheduling.index', ['date' => $today->copy()->addDay()->toDateString()]) }}"
                class="flex h-11 w-11 items-center justify-center rounded-xl bg-surface-container text-on-surface transition-colors hover:bg-surface-container-high"
                aria-label="{{ __('اليوم التالي') }}">
                <span class="material-symbols-outlined rtl:-scale-x-100">chevron_right</span>
            </a>

            <span class="font-body-lg font-bold text-on-surface">{{ $today->translatedFormat('l j F Y') }}</span>
        </form>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ($resources as $resource)
                @php $bookings = $byResource[$resource->id] ?? collect(); @endphp
                <x-card class="reveal-up" style="--d:{{ min($loop->index, 11) * 40 + 100 }}ms">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-headline-md text-headline-md">{{ $resource->name }}</h3>
                            <p class="font-body-sm text-outline">{{ $resource->kindLabel() }}</p>
                        </div>
                        <span class="whitespace-nowrap font-numeral-lg font-bold text-on-surface-variant">{{ $bookings->count() }}</span>
                    </div>

                    @if ($bookings->isEmpty())
                        <p class="rounded-xl bg-surface-container-low p-4 text-center font-body-sm text-on-surface-variant">{{ __('لا مواعيد اليوم') }}</p>
                    @else
                        <ul class="space-y-2">
                            @foreach ($bookings as $booking)
                                <li class="rounded-2xl border border-outline-variant p-3">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="font-body-lg font-bold">{{ $booking->customer_name }}</p>
                                            <p class="font-body-sm text-on-surface-variant">
                                                <span dir="ltr">{{ $booking->timeLabel() }}</span>
                                                @if ($booking->item)
                                                    — {{ $booking->item->name }}
                                                @endif
                                            </p>
                                        </div>
                                        <span class="whitespace-nowrap rounded-lg px-2 py-1 font-body-sm font-bold {{ $booking->status->tone() }}">{{ $booking->status->label() }}</span>
                                    </div>

                                    <form method="POST" action="{{ route('scheduling.update', $booking->id) }}" class="mt-2 flex items-center gap-2">
                                        @csrf
                                        @method('PUT')
                                        <label for="status-{{ $booking->id }}" class="sr-only">{{ __('الحالة') }}</label>
                                        <select id="status-{{ $booking->id }}" name="status" onchange="this.form.submit()"
                                            class="flex-1 rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-1.5 font-body-sm outline-none focus:ring-2 focus:ring-primary">
                                            @foreach ($statuses as $case)
                                                <option value="{{ $case->value }}" @selected($booking->status === $case)>{{ $case->label() }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @php $slots = $openSlots[$resource->id] ?? []; @endphp
                    @if ($slots !== [])
                        <p class="mt-3 font-body-sm text-outline">
                            {{ __('أوقات متاحة') }}:
                            @foreach (array_slice($slots, 0, 4) as $slot)
                                <span class="ms-1 inline-block rounded-lg bg-success/10 px-2 py-0.5 text-success" dir="ltr">
                                    {{ $slot['from']->format('H:i') }}–{{ $slot['to']->format('H:i') }}
                                </span>
                            @endforeach
                        </p>
                    @endif
                </x-card>
            @endforeach
        </div>

        <div x-show="open" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="booking-title">
            <div class="fixed inset-0 bg-black/40" x-show="open" x-transition.opacity @click="open = false"></div>
            <div class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                x-show="open" @keydown.escape.window="open = false"
                x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

                <x-modal-close on-close="open = false" />

                <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
                    <h3 id="booking-title" class="font-headline-md text-headline-md">{{ __('حجز موعد') }}</h3>
                    <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">{{ __('يُرفض الحجز إذا تعارض مع موعد آخر لنفس المورد.') }}</p>
                </div>

                <form method="POST" action="{{ route('scheduling.store') }}" class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2" @submit="submitting = true">
                    @csrf

                    <div class="space-y-1.5">
                        <label for="booking-customer" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اسم العميل') }}</label>
                        <input id="booking-customer" name="customerName" value="{{ old('customerName') }}" required maxlength="255"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        @error('customerName')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="booking-phone" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الهاتف') }}</label>
                        <input id="booking-phone" name="customerPhone" value="{{ old('customerPhone') }}" dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="booking-service" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الخدمة') }}</label>
                        <select id="booking-service" name="itemId"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            <option value="">{{ __('بدون تحديد') }}</option>
                            @foreach ($services as $service)
                                <option value="{{ $service->id }}" @selected(old('itemId') === $service->id)>
                                    {{ $service->name }}@if ($service->service_minutes) ({{ __(':count دقيقة', ['count' => $service->service_minutes]) }})@endif
                                </option>
                            @endforeach
                        </select>
                        <p class="font-body-sm text-outline">{{ __('مدّة الخدمة تحدّد وقت الانتهاء تلقائيًا.') }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <label for="booking-resource" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المورد') }}</label>
                        <select id="booking-resource" name="resourceId"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            <option value="">{{ __('بدون تحديد') }}</option>
                            @foreach ($resources as $resource)
                                <option value="{{ $resource->id }}" @selected(old('resourceId') === $resource->id)>{{ $resource->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label for="booking-start" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('يبدأ') }}</label>
                        <input id="booking-start" type="datetime-local" name="startsAt" value="{{ old('startsAt', $today->copy()->setTime(9, 0)->format('Y-m-d\TH:i')) }}" required dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        @error('startsAt')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="booking-end" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('ينتهي (اختياري)') }}</label>
                        <input id="booking-end" type="datetime-local" name="endsAt" value="{{ old('endsAt') }}" dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="booking-deposit" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('العربون') }}</label>
                        <input id="booking-deposit" type="number" step="0.01" min="0" name="deposit" value="{{ old('deposit') }}" dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        <p class="font-body-sm text-outline">{{ __('ليس إيرادًا حتى تُقدَّم الخدمة (IFRS 15).') }}</p>
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <label for="booking-notes" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('ملاحظات') }}</label>
                        <textarea id="booking-notes" name="notes" rows="2" maxlength="1000"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">{{ old('notes') }}</textarea>
                    </div>

                    <div class="-mx-6 -mb-6 mt-2 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4 sm:col-span-2">
                        <button type="button" @click="open = false" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                            x-text="submitting ? @js(__('جارٍ الحجز...')) : @js(__('احجز'))">{{ __('احجز') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
