@extends('layouts.admin')

@section('title', __('المالية - حوالاتي'))

@section('content')
    @php $totalBoxBalance = collect($summary['boxes'])->sum('balance'); @endphp
    <div class="page-enter space-y-8"
        x-data="{
            withdrawalOpen: {{ old('partnerId') ? 'true' : 'false' }},
            expenseOpen: {{ old('description') ? 'true' : 'false' }},
            submitting: false,
        }">

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('المالية') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('محاسبة المنصّة الداخلية: الصناديق والشركاء والحركات.') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="expenseOpen = true; submitting = false" @disabled($boxes->isEmpty())
                    class="flex items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2.5 font-body-sm font-bold text-on-surface transition-all hover:bg-surface-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50">
                    <span class="material-symbols-outlined !text-xl">shopping_bag</span>{{ __('تسجيل مصروف') }}
                </button>
                <button type="button" @click="withdrawalOpen = true; submitting = false" @disabled($boxes->isEmpty() || $partners->isEmpty())
                    class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50">
                    <span class="material-symbols-outlined !text-xl">call_made</span>{{ __('تسجيل سحب') }}
                </button>
            </div>
        </header>

        {{-- Summary tiles --}}
        <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <x-card>
                <p class="font-body-sm text-on-surface-variant">{{ __('إجمالي المدفوعات (داخل)') }}</p>
                <p class="mt-2 font-numeral-lg text-numeral-lg font-bold text-primary"><span dir="ltr">{{ number_format($summary['totalPayments'], 2) }} ₪</span></p>
            </x-card>
            <x-card>
                <p class="font-body-sm text-on-surface-variant">{{ __('إجمالي المصروفات (خارج)') }}</p>
                <p class="mt-2 font-numeral-lg text-numeral-lg font-bold text-error"><span dir="ltr">{{ number_format($summary['totalExpenses'], 2) }} ₪</span></p>
            </x-card>
            <x-card>
                <p class="font-body-sm text-on-surface-variant">{{ __('إجمالي أرصدة الصناديق') }}</p>
                <p class="mt-2 font-numeral-lg text-numeral-lg font-bold"><span dir="ltr">{{ number_format($totalBoxBalance, 2) }} ₪</span></p>
            </x-card>
        </section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Boxes --}}
            <x-card title="{{ __('الصناديق') }}" subtitle="{{ __('أرصدة حسابات المنصّة') }}">
                @if (empty($summary['boxes']))
                    <p class="py-8 text-center font-body-sm text-on-surface-variant">{{ __('لا توجد صناديق بعد') }}</p>
                @else
                    <ul class="divide-y divide-outline-variant/50">
                        @foreach ($summary['boxes'] as $box)
                            <li class="flex items-center justify-between gap-3 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-primary">account_balance_wallet</span>
                                    <span class="font-body-sm font-bold">{{ $box['name'] }}</span>
                                </div>
                                <span class="font-bold" dir="ltr">{{ number_format($box['balance'], 2) }} ₪</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            {{-- Partners --}}
            <x-card title="{{ __('الشركاء') }}" subtitle="{{ __('الحصة المحسوبة من إجمالي أرصدة الصناديق') }}">
                @if (empty($summary['partners']))
                    <p class="py-8 text-center font-body-sm text-on-surface-variant">{{ __('لا يوجد شركاء بعد') }}</p>
                @else
                    <ul class="divide-y divide-outline-variant/50">
                        @foreach ($summary['partners'] as $partner)
                            <li class="flex items-center justify-between gap-3 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-secondary">handshake</span>
                                    <div>
                                        <p class="font-body-sm font-bold">{{ $partner['name'] }}</p>
                                        <p class="text-[11px] text-on-surface-variant">{{ number_format($partner['percentage'], 2) }}%</p>
                                    </div>
                                </div>
                                <span class="font-bold text-secondary" dir="ltr">{{ number_format($partner['computedShare'], 2) }} ₪</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        {{-- Recent payments --}}
        <x-card title="{{ __('آخر المدفوعات') }}">
            @if ($recentPayments->isEmpty())
                <p class="py-8 text-center font-body-sm text-on-surface-variant">{{ __('لا توجد مدفوعات بعد') }}</p>
            @else
                <x-table-responsive :headers="[__('الترخيص'), __('الصندوق'), __('التاريخ'), __('المبلغ')]">
                    <x-slot:table>
                        @foreach ($recentPayments as $payment)
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm font-bold">{{ $payment->license?->customer_name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $payment->box?->box_name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $payment->created_at?->translatedFormat('j F Y') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-bold text-primary"><span dir="ltr">+{{ number_format($payment->amount, 2) }} ₪</span></td>
                            </tr>
                        @endforeach
                    </x-slot:table>
                    <x-slot:cards>
                        @foreach ($recentPayments as $payment)
                            <div class="tonal-elevation rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-center justify-between">
                                    <p class="font-body-sm font-bold">{{ $payment->license?->customer_name ?? '—' }}</p>
                                    <p class="font-bold text-primary" dir="ltr">+{{ number_format($payment->amount, 2) }} ₪</p>
                                </div>
                                <p class="mt-1 text-[11px] text-on-surface-variant">{{ $payment->box?->box_name ?? '—' }} · {{ $payment->created_at?->translatedFormat('j M Y') }}</p>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>
            @endif
        </x-card>

        {{-- Withdrawal modal --}}
        <div x-show="withdrawalOpen" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
            <div class="fixed inset-0 bg-black/40" x-show="withdrawalOpen" x-transition.opacity></div>
            <div x-show="withdrawalOpen" @keydown.escape.window="withdrawalOpen = false" role="dialog" aria-modal="true"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto overflow-x-hidden rounded-3xl bg-surface-container-lowest shadow-2xl">
                <h3 class="border-b border-outline-variant/60 px-6 py-4 font-headline-md text-headline-md">{{ __('تسجيل سحب لشريك') }}</h3>
                <form method="POST" action="{{ route('admin.finance.withdrawals.store') }}" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf
                    <div class="space-y-1.5">
                        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الصندوق') }}</label>
                        <select name="boxId" required class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            <option value="" disabled selected>{{ __('اختر...') }}</option>
                            @foreach ($boxes as $box)
                                <option value="{{ $box->id }}" @selected(old('boxId') === $box->id)>{{ $box->box_name }} — {{ number_format($box->box_balance, 2) }} ₪</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الشريك') }}</label>
                        <select name="partnerId" required class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            <option value="" disabled selected>{{ __('اختر...') }}</option>
                            @foreach ($partners as $partner)
                                <option value="{{ $partner->id }}" @selected(old('partnerId') === $partner->id)>{{ $partner->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المبلغ (₪)') }}</label>
                        <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required dir="ltr" placeholder="0.00"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="withdrawalOpen = false" class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container" title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting" class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:opacity-50" x-text="submitting ? '{{ __('جارٍ الحفظ...') }}' : '{{ __('تسجيل') }}'"></button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Expense modal --}}
        <div x-show="expenseOpen" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
            <div class="fixed inset-0 bg-black/40" x-show="expenseOpen" x-transition.opacity></div>
            <div x-show="expenseOpen" @keydown.escape.window="expenseOpen = false" role="dialog" aria-modal="true"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto overflow-x-hidden rounded-3xl bg-surface-container-lowest shadow-2xl">
                <h3 class="border-b border-outline-variant/60 px-6 py-4 font-headline-md text-headline-md">{{ __('تسجيل مصروف تشغيلي') }}</h3>
                <form method="POST" action="{{ route('admin.finance.expenses.store') }}" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf
                    <div class="space-y-1.5">
                        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الوصف') }}</label>
                        <input name="description" value="{{ old('description') }}" required placeholder="{{ __('مثال: استضافة، دومين...') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المبلغ (₪)') }}</label>
                            <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required dir="ltr" placeholder="0.00"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        </div>
                        <div class="space-y-1.5">
                            <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('التاريخ') }}</label>
                            <input type="date" name="date" value="{{ old('date', now()->toDateString()) }}" required dir="ltr"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الصندوق') }}</label>
                        <select name="boxId" required class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            <option value="" disabled selected>{{ __('اختر...') }}</option>
                            @foreach ($boxes as $box)
                                <option value="{{ $box->id }}" @selected(old('boxId') === $box->id)>{{ $box->box_name }} — {{ number_format($box->box_balance, 2) }} ₪</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="expenseOpen = false" class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container" title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting" class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:opacity-50" x-text="submitting ? '{{ __('جارٍ الحفظ...') }}' : '{{ __('تسجيل') }}'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
