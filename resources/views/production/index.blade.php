@extends('layouts.app')

@section('title', __('الإنتاج - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }}, submitting: false }">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('أوامر الإنتاج') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('دفعة الخبز تستهلك الطحين وتُنتج أرغفة — حدثٌ واحد يحرّك المخزون في الاتجاهين.') }}</p>
            </div>
            @if ($producible->isNotEmpty())
                <button type="button" @click="open = true"
                    class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                    <span class="material-symbols-outlined !text-xl">add</span>
                    {{ __('أمر إنتاج') }}
                </button>
            @endif
        </header>

        @if ($producible->isEmpty())
            <div class="flex items-start gap-3 rounded-2xl border border-secondary-container/40 bg-secondary-container/10 p-4 text-secondary">
                <span class="material-symbols-outlined flex-shrink-0">info</span>
                <p class="font-body-sm">{{ __('لا يوجد صنف قابل للإنتاج. الصنف المنتَج يجب أن تكون له وصفة وأن يخضع لتتبّع المخزون.') }}</p>
            </div>
        @endif

        <form method="GET" action="{{ route('production.index') }}" class="reveal-up max-w-xs" style="--d:60ms">
            <x-keep-per-page />
            <select name="status" onchange="this.form.submit()" aria-label="{{ __('الحالة') }}"
                class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                <option value="">{{ __('كل الحالات') }}</option>
                <option value="draft" @selected(($status ?? '') === 'draft')>{{ __('مسودة') }}</option>
                <option value="posted" @selected(($status ?? '') === 'posted')>{{ __('مُرحَّل') }}</option>
                <option value="cancelled" @selected(($status ?? '') === 'cancelled')>{{ __('ملغى') }}</option>
            </select>
        </form>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($runs->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">factory</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد أوامر إنتاج بعد') }}</p>
                </div>
            @else
                @php
                    $badge = fn ($s) => match ($s) {
                        'posted' => 'bg-success/10 text-success',
                        'cancelled' => 'bg-error/10 text-error',
                        default => 'bg-secondary-container/30 text-secondary',
                    };
                @endphp

                <x-table-responsive :headers="[
                    __('الصنف'),
                    ['label' => __('الكمية'), 'align' => 'end'],
                    ['label' => __('تكلفة الوحدة'), 'align' => 'end'],
                    __('الموقع'),
                    __('التاريخ'),
                    __('الحالة'),
                    __(''),
                ]">
                    <x-slot:table>
                        @foreach ($runs as $run)
                            <tr class="reveal-up transition-colors hover:bg-surface-container-low/50" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <td class="px-4 py-3">
                                    <p class="font-body-sm font-bold">{{ $run->item?->name ?? '—' }}</p>
                                    @if ($run->batch_number)
                                        <p class="font-body-sm text-outline" dir="ltr">{{ $run->batch_number }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold" dir="ltr">{{ number_format((float) $run->quantity, 3) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ $run->isDraft() ? '—' : number_format((float) $run->unit_cost, 3) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $run->location?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ ($run->produced_at ?? $run->created_at)?->translatedFormat('j M Y') }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="rounded-lg px-2 py-1 font-body-sm font-bold {{ $badge($run->status) }}">{{ $run->statusLabel() }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('production.show', $run->id) }}"
                                        class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                                        aria-label="{{ __('فتح أمر الإنتاج') }}">
                                        <span class="material-symbols-outlined !text-xl rtl:-scale-x-100">chevron_right</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($runs as $run)
                            <a href="{{ route('production.show', $run->id) }}" class="reveal-up block rounded-2xl border border-outline-variant bg-surface-container-lowest p-4" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-body-lg font-bold">{{ $run->item?->name }}</p>
                                        <p class="font-body-sm text-outline" dir="ltr">{{ number_format((float) $run->quantity, 3) }}</p>
                                    </div>
                                    <span class="whitespace-nowrap rounded-lg px-2 py-1 font-body-sm font-bold {{ $badge($run->status) }}">{{ $run->statusLabel() }}</span>
                                </div>
                            </a>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$runs" :perPage="$perPage" />
            @endif
        </section>

        @if ($producible->isNotEmpty())
            <div x-show="open" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="run-dialog-title">
                <div class="fixed inset-0 bg-black/40" x-show="open" x-transition.opacity @click="open = false"></div>
                <div class="relative z-10 max-h-[90vh] w-full max-w-md overflow-y-auto rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                    x-show="open" @keydown.escape.window="open = false"
                    x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

                    <x-modal-close on-close="open = false" />

                    <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
                        <h3 id="run-dialog-title" class="font-headline-md text-headline-md">{{ __('أمر إنتاج جديد') }}</h3>
                        <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">{{ __('يُحفظ كمسودة. لا يتحرك المخزون إلا عند الترحيل.') }}</p>
                    </div>

                    <form method="POST" action="{{ route('production.store') }}" class="space-y-4 p-6" @submit="submitting = true">
                        @csrf

                        <div class="space-y-1.5">
                            <label for="run-item" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الصنف المنتَج') }}</label>
                            <select id="run-item" name="itemId" required
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                @foreach ($producible as $candidate)
                                    <option value="{{ $candidate->id }}" @selected(old('itemId') === $candidate->id)>{{ $candidate->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label for="run-quantity" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الكمية المنتَجة') }}</label>
                            <input id="run-quantity" type="number" step="0.001" min="0" name="quantity" value="{{ old('quantity') }}" required dir="ltr"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            @error('quantity')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="run-location" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الموقع') }}</label>
                            <select id="run-location" name="locationId" required
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}" @selected($location->is_default)>{{ $location->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if ($tracksBatches)
                            <div class="space-y-1.5">
                                <label for="run-batch" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('رقم الدفعة') }}</label>
                                <input id="run-batch" name="batchNumber" value="{{ old('batchNumber') }}" required maxlength="64" dir="ltr"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                                @error('batchNumber')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="space-y-1.5">
                                <label for="run-expiry" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('تنتهي في') }}</label>
                                <input id="run-expiry" type="date" name="expiresAt" value="{{ old('expiresAt', now()->addDay()->toDateString()) }}" required dir="ltr"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                                @error('expiresAt')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
                            </div>
                        @endif

                        <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                            <button type="button" @click="open = false" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                            <button type="submit" :disabled="submitting"
                                class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                                x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ كمسودة'))">{{ __('حفظ كمسودة') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection
