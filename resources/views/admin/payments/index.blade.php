@extends('layouts.admin')

@section('title', __('المدفوعات - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6"
        x-data="{ open: {{ $errors->any() ? 'true' : 'false' }}, submitting: false }">

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('المدفوعات') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('سجّل مدفوعات العملاء — تُجدّد الترخيص وتزيد رصيد الصندوق تلقائياً.') }}</p>
            </div>
            <button type="button" @click="open = true; submitting = false" @disabled($boxes->isEmpty() || $licenses->isEmpty())
                class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50">
                <span class="material-symbols-outlined !text-xl">add</span>{{ __('تسجيل دفعة') }}
            </button>
        </header>

        @if ($boxes->isEmpty() || $licenses->isEmpty())
            <div class="flex items-center gap-3 rounded-2xl border border-secondary-container/40 bg-secondary-container/10 p-4 text-secondary">
                <span class="material-symbols-outlined">info</span>
                <p class="font-body-sm">{{ __('يلزم وجود صندوق واحد وترخيص واحد على الأقل لتسجيل الدفعات.') }}</p>
            </div>
        @endif

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($payments->isEmpty())
                <div class="flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">payments</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد مدفوعات بعد') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[__('الترخيص'), __('الصندوق'), __('التاريخ'), __('المبلغ')]">
                    <x-slot:table>
                        @foreach ($payments as $payment)
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm font-bold">{{ $payment->license?->customer_name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $payment->box?->box_name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $payment->created_at?->translatedFormat('j F Y') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-bold text-primary"><span dir="ltr">+{{ number_format($payment->amount, 2) }} ₪</span></td>
                            </tr>
                        @endforeach
                    </x-slot:table>
                    <x-slot:cards>
                        @foreach ($payments as $payment)
                            <div class="tonal-elevation rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-center justify-between">
                                    <span class="font-body-sm font-bold">{{ $payment->license?->customer_name ?? '—' }}</span>
                                    <span class="font-bold text-primary" dir="ltr">+{{ number_format($payment->amount, 2) }} ₪</span>
                                </div>
                                <p class="mt-1 text-[11px] text-on-surface-variant">{{ $payment->box?->box_name ?? '—' }} · {{ $payment->created_at?->translatedFormat('j M Y') }}</p>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$payments" :perPage="$perPage" />
            @endif
        </section>

        <div x-show="open" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
            <div class="fixed inset-0 bg-black/40" x-show="open" x-transition.opacity></div>
            <div x-show="open" @keydown.escape.window="open = false" role="dialog" aria-modal="true"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto overflow-x-hidden rounded-3xl bg-surface-container-lowest shadow-2xl">
                <h3 class="border-b border-outline-variant/60 px-6 py-4 font-headline-md text-headline-md">{{ __('تسجيل دفعة') }}</h3>
                <form method="POST" action="{{ route('admin.payments.store') }}" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf
                    <div class="space-y-1.5">
                        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الترخيص') }}</label>
                        <select name="licenseId" required class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            <option value="" disabled selected>{{ __('اختر...') }}</option>
                            @foreach ($licenses as $license)
                                <option value="{{ $license->id }}" @selected(old('licenseId') === $license->id)>{{ $license->customer_name }} ({{ $license->license_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الصندوق المستلِم') }}</label>
                        <select name="boxId" required class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            <option value="" disabled selected>{{ __('اختر...') }}</option>
                            @foreach ($boxes as $box)
                                <option value="{{ $box->id }}" @selected(old('boxId') === $box->id)>{{ $box->box_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المبلغ (₪)') }}</label>
                        <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required dir="ltr" placeholder="0.00" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                    <p class="flex items-center gap-1.5 font-body-sm text-on-surface-variant"><span class="material-symbols-outlined !text-lg">info</span>{{ __('ستُجدَّد صلاحية الترخيص 30 يوماً.') }}</p>
                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="open = false" class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container" title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting" class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:opacity-50" x-text="submitting ? '{{ __('جارٍ الحفظ...') }}' : '{{ __('تسجيل') }}'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
