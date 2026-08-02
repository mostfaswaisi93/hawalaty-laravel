@extends('layouts.app')

@section('title', __('الجرد - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }}, submitting: false }">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الجرد الفعلي') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('الآلية الوحيدة لضبط الفاقد والسرقة — عدّ الرف ومقارنته بالدفتر.') }}</p>
            </div>
            <button type="button" @click="open = true"
                class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                <span class="material-symbols-outlined !text-xl">add</span>
                {{ __('فتح جلسة جرد') }}
            </button>
        </header>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($counts->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">checklist</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لم تُفتح أي جلسة جرد بعد') }}</p>
                </div>
            @else
                @php
                    $badge = fn ($s) => match ($s) {
                        'posted' => 'bg-success/10 text-success',
                        'cancelled' => 'bg-error/10 text-error',
                        default => 'bg-secondary-container/30 text-secondary',
                    };
                @endphp

                <x-table-responsive :headers="[__('الجلسة'), __('الموقع'), ['label' => __('الأسطر'), 'align' => 'end'], __('بواسطة'), __('الحالة'), __('')]">
                    <x-slot:table>
                        @foreach ($counts as $count)
                            <tr class="reveal-up transition-colors hover:bg-surface-container-low/50" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <td class="px-4 py-3">
                                    <p class="font-body-sm font-bold" dir="ltr">{{ $count->reference ?: mb_substr($count->id, -8) }}</p>
                                    <p class="font-body-sm text-outline">{{ $count->created_at?->translatedFormat('j M Y - H:i') }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm">{{ $count->location?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg">{{ $count->lines_count }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $count->teamMember?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="rounded-lg px-2 py-1 font-body-sm font-bold {{ $badge($count->status) }}">{{ $count->statusLabel() }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('stock-counts.show', $count->id) }}"
                                        class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                                        aria-label="{{ __('فتح جلسة الجرد') }}">
                                        <span class="material-symbols-outlined !text-xl rtl:-scale-x-100">chevron_right</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($counts as $count)
                            <a href="{{ route('stock-counts.show', $count->id) }}" class="reveal-up block rounded-2xl border border-outline-variant bg-surface-container-lowest p-4" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-body-lg font-bold" dir="ltr">{{ $count->reference ?: mb_substr($count->id, -8) }}</p>
                                        <p class="font-body-sm text-outline">{{ $count->location?->name }}</p>
                                    </div>
                                    <span class="whitespace-nowrap rounded-lg px-2 py-1 font-body-sm font-bold {{ $badge($count->status) }}">{{ $count->statusLabel() }}</span>
                                </div>
                            </a>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$counts" :perPage="$perPage" />
            @endif
        </section>

        <div x-show="open" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="count-dialog-title">
            <div class="fixed inset-0 bg-black/40" x-show="open" x-transition.opacity @click="open = false"></div>
            <div class="relative z-10 w-full max-w-md rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                x-show="open" @keydown.escape.window="open = false"
                x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

                <x-modal-close on-close="open = false" />

                <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
                    <h3 id="count-dialog-title" class="font-headline-md text-headline-md">{{ __('فتح جلسة جرد') }}</h3>
                    <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">{{ __('تُلتقط الأرصدة الدفترية الآن حتى يبقى الفرق ذا معنى رغم استمرار البيع.') }}</p>
                </div>

                <form method="POST" action="{{ route('stock-counts.store') }}" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf

                    <div class="space-y-1.5">
                        <label for="count-location" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الموقع') }}</label>
                        <select id="count-location" name="locationId" required
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected($location->is_default)>{{ $location->name }}</option>
                            @endforeach
                        </select>
                        @error('locationId')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="count-reference" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اسم الجلسة') }}</label>
                        <input id="count-reference" name="reference" maxlength="64" placeholder="{{ __('مثال: جرد نهاية الشهر') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="open = false" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                            x-text="submitting ? @js(__('جارٍ الفتح...')) : @js(__('فتح الجلسة'))">{{ __('فتح الجلسة') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
