@extends('layouts.app')

@section('title', $account->holder_name . ' - ' . __('حوالاتي'))

@php $symbol = $account->currency?->symbol ?? ''; @endphp

@section('content')
    <div class="page-enter space-y-6"
        x-data="{
            entryOpen: false, submitting: false, direction: 'debit',
            confirmOpen: false, confirmMessage: '', confirmFormId: null,
        }">

        {{-- ══════════════ header ══════════════ --}}
        <header class="flex flex-col gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('accounts.index') }}" class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('رجوع') }}" title="{{ __('رجوع') }}">
                    <span class="material-symbols-outlined rtl:-scale-x-100">arrow_back</span>
                </a>
                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <span class="material-symbols-outlined">{{ $account->kind->icon() }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate font-display-lg text-2xl font-bold text-on-surface md:text-display-lg">{{ $account->holder_name }}</h1>
                    <p class="font-body-lg text-on-surface-variant">
                        {{ $account->kind->label() }}
                        @if ($account->institutionName()) · {{ $account->institutionName() }}@endif
                        @if ($account->typeName()) · {{ $account->typeName() }}@endif
                        @unless ($account->active) · <span class="font-bold text-error">{{ __('معطّل') }}</span>@endunless
                    </p>
                </div>
                <div class="flex items-center gap-1">
                    <a href="{{ route('accounts.edit', $account->id) }}" class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تعديل') }}" title="{{ __('تعديل') }}">
                        <span class="material-symbols-outlined !text-xl">edit</span>
                    </a>
                    <form id="delete-account" method="POST" action="{{ route('accounts.destroy', $account->id) }}" class="inline-flex">@csrf @method('DELETE')</form>
                    <button type="button" @click="confirmOpen = true; confirmMessage = '{{ __('هل أنت متأكد من حذف هذا الحساب؟') }}'; confirmFormId = 'delete-account'"
                        class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error" aria-label="{{ __('حذف') }}" title="{{ __('حذف') }}">
                        <span class="material-symbols-outlined !text-xl">delete</span>
                    </button>
                </div>
            </div>
        </header>

        {{-- ══════════════ balance + details ══════════════ --}}
        <div class="grid gap-5 lg:grid-cols-3">
            <section class="reveal-up tonal-elevation flex flex-col justify-center rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-6">
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الرصيد الحالي') }}</p>
                <p class="mt-2 font-display-lg text-4xl font-bold {{ (float) $account->balance < 0 ? 'text-error' : 'text-on-surface' }}">
                    <x-money :amount="$account->balance" :symbol="$symbol" />
                </p>
                <p class="mt-1 font-body-sm text-outline">{{ $account->currency?->name }}</p>
            </section>

            <section class="reveal-up tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-6 lg:col-span-2" style="--d:60ms">
                <h2 class="mb-4 font-headline-md text-headline-md text-on-surface">{{ __('تفاصيل الحساب') }}</h2>
                <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                    @if ($account->kind === \App\Enums\AccountKind::Cash)
                        <div><dt class="font-label-caps text-label-caps uppercase tracking-wider text-outline">{{ __('الصندوق النقدي') }}</dt><dd class="font-body-lg text-on-surface">{{ $account->cashBox?->name ?: '-' }}</dd></div>
                    @elseif ($account->kind === \App\Enums\AccountKind::Bank)
                        <div><dt class="font-label-caps text-label-caps uppercase tracking-wider text-outline">{{ __('آيبان') }}</dt><dd class="font-body-lg text-on-surface" dir="ltr">{{ $account->iban ?: '-' }}</dd></div>
                    @else
                        <div><dt class="font-label-caps text-label-caps uppercase tracking-wider text-outline">{{ __('رقم هاتف المحفظة') }}</dt><dd class="font-body-lg text-on-surface" dir="ltr">{{ $account->wallet_phone ?: '-' }}</dd></div>
                    @endif
                    <div><dt class="font-label-caps text-label-caps uppercase tracking-wider text-outline">{{ __('العملة') }}</dt><dd class="font-body-lg text-on-surface">{{ $account->currency?->name }} <span dir="ltr">({{ $symbol }})</span></dd></div>
                    <div><dt class="font-label-caps text-label-caps uppercase tracking-wider text-outline">{{ __('رقم الهاتف') }}</dt><dd class="font-body-lg text-on-surface" dir="ltr">{{ $account->phone ?: '-' }}</dd></div>
                    <div><dt class="font-label-caps text-label-caps uppercase tracking-wider text-outline">{{ __('رقم واتساب') }}</dt><dd class="font-body-lg text-on-surface" dir="ltr">{{ $account->whatsapp_phone ?: '-' }}</dd></div>
                </dl>
            </section>
        </div>

        {{-- ══════════════ statement ══════════════ --}}
        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            <div class="mb-5 flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-headline-md text-headline-md text-on-surface">{{ __('كشف الحركات') }}</h2>
                    <p class="mt-0.5 font-body-sm text-outline">{{ __('مدين / دائن / الرصيد') }}</p>
                </div>
                <button type="button" @click="entryOpen = true; submitting = false; direction = 'debit'"
                    class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95" title="{{ __('إضافة حركة') }}">
                    <span class="material-symbols-outlined !text-xl">add</span>
                    {{ __('إضافة حركة') }}
                </button>
            </div>

            @if ($entries->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-12 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">receipt_long</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا حركات بعد') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[__('التاريخ'), __('البيان'), ['label' => __('مدين'), 'align' => 'end'], ['label' => __('دائن'), 'align' => 'end'], ['label' => __('الرصيد'), 'align' => 'end']]">
                    <x-slot:table>
                        @foreach ($entries as $entry)
                            <tr class="reveal-up transition-colors hover:bg-surface-container-low/50" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant"><span dir="ltr">{{ $entry->created_at?->format('Y-m-d H:i') }}</span></td>
                                <td class="px-4 py-3 font-body-sm">{{ $entry->description ?: '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-bold {{ (float) $entry->debit > 0 ? 'text-success' : 'text-outline' }}">
                                    @if ((float) $entry->debit > 0)<x-money :amount="$entry->debit" :symbol="$symbol" />@else - @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-bold {{ (float) $entry->credit > 0 ? 'text-error' : 'text-outline' }}">
                                    @if ((float) $entry->credit > 0)<x-money :amount="$entry->credit" :symbol="$symbol" />@else - @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-bold {{ (float) $entry->running_balance < 0 ? 'text-error' : 'text-on-surface' }}">
                                    <x-money :amount="$entry->running_balance" :symbol="$symbol" />
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>
                    <x-slot:cards>
                        @foreach ($entries as $entry)
                            <div class="reveal-up tonal-elevation rounded-2xl border border-outline-variant bg-surface-container-lowest p-4" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-body-sm font-bold">{{ $entry->description ?: '-' }}</p>
                                        <p class="text-[11px] text-outline" dir="ltr">{{ $entry->created_at?->format('Y-m-d H:i') }}</p>
                                    </div>
                                    <span class="whitespace-nowrap font-bold {{ (float) $entry->debit > 0 ? 'text-success' : 'text-error' }}">
                                        {{ (float) $entry->debit > 0 ? '+' : '-' }}<x-money :amount="(float) $entry->debit > 0 ? $entry->debit : $entry->credit" :symbol="$symbol" />
                                    </span>
                                </div>
                                <div class="mt-2 flex items-center justify-between border-t border-outline-variant/50 pt-2 text-[11px]">
                                    <span class="text-outline">{{ __('الرصيد') }}</span>
                                    <span class="font-bold {{ (float) $entry->running_balance < 0 ? 'text-error' : 'text-on-surface' }}"><x-money :amount="$entry->running_balance" :symbol="$symbol" /></span>
                                </div>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$entries" :perPage="$perPage" />
            @endif
        </section>

        {{-- ══════════════ add-entry modal ══════════════ --}}
        <div x-show="entryOpen" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
            <div class="fixed inset-0 bg-black/40" x-show="entryOpen" x-transition.opacity @click="entryOpen = false"></div>
            <div x-show="entryOpen" @keydown.escape.window="entryOpen = false"
                x-init="$watch('entryOpen', v => v && $nextTick(() => $el.querySelector('input[name=amount]')?.focus()))"
                role="dialog" aria-modal="true" aria-labelledby="entry-modal-title"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto overflow-x-hidden rounded-3xl bg-surface-container-lowest shadow-2xl">
                <x-modal-close on-close="entryOpen = false" />
                <h3 id="entry-modal-title" class="border-b border-outline-variant/60 py-4 ps-6 pe-14 font-headline-md text-headline-md">{{ __('إضافة حركة') }}</h3>

                <form method="POST" action="{{ route('accounts.entries.store', $account->id) }}" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf
                    <input type="hidden" name="direction" :value="direction">

                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" @click="direction = 'debit'"
                            class="flex flex-col items-center gap-1 rounded-2xl border-2 p-4 transition-all"
                            :class="direction === 'debit' ? 'border-success bg-success-container/30 text-success' : 'border-outline-variant text-on-surface-variant hover:border-outline'">
                            <span class="material-symbols-outlined">south_west</span>
                            <span class="font-body-sm font-bold">{{ __('مدين') }}</span>
                        </button>
                        <button type="button" @click="direction = 'credit'"
                            class="flex flex-col items-center gap-1 rounded-2xl border-2 p-4 transition-all"
                            :class="direction === 'credit' ? 'border-error bg-error-container/30 text-error' : 'border-outline-variant text-on-surface-variant hover:border-outline'">
                            <span class="material-symbols-outlined">north_east</span>
                            <span class="font-body-sm font-bold">{{ __('دائن') }}</span>
                        </button>
                    </div>

                    <div class="space-y-1.5">
                        <label for="entry-amount" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المبلغ') }} <span dir="ltr">({{ $symbol }})</span></label>
                        <input id="entry-amount" name="amount" type="number" step="0.01" min="0.01" required dir="ltr" placeholder="0.00"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="entry-desc" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('البيان (اختياري)') }}</label>
                        <input id="entry-desc" name="description" maxlength="255" placeholder="{{ __('مثال: إيداع نقدي') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="entryOpen = false"
                            class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container" title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                            x-text="submitting ? '{{ __('جارٍ الحفظ...') }}' : '{{ __('إضافة') }}'"></button>
                    </div>
                </form>
            </div>
        </div>

        <x-confirm-modal />
    </div>
@endsection
