@extends('layouts.app')

@section('title', __('مرتجع جديد - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6">
        <header>
            <a href="{{ route('returns.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                {{ __('المرتجعات') }}
            </a>
            <h1 class="mt-1 font-display-lg text-display-lg text-on-surface">{{ __('مرتجع جديد') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('ابدأ من البيعة الأصلية — هي وحدها ما يجعل الكميات والأسعار موثوقة.') }}</p>
        </header>

        <x-card title="{{ __('ابحث عن البيعة') }}" class="reveal-up" style="--d:60ms">
            <form method="GET" action="{{ route('returns.create') }}" class="relative">
                <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">search</span>
                <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('اسم العميل أو رقم الحوالة...') }}" aria-label="{{ __('بحث') }}" autofocus
                    class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
            </form>

            @if ($search !== '' && $matches->isNotEmpty())
                <ul class="mt-4 divide-y divide-outline-variant/60 rounded-2xl border border-outline-variant">
                    @foreach ($matches as $match)
                        <li>
                            <a href="{{ route('returns.create', ['transferId' => $match->id]) }}"
                                class="flex items-center justify-between gap-3 px-4 py-3 transition-colors hover:bg-surface-container-low">
                                <div class="min-w-0">
                                    <p class="font-body-lg font-bold">{{ $match->customer }}</p>
                                    <p class="font-body-sm text-outline">{{ $match->created_at?->translatedFormat('j M Y - H:i') }}</p>
                                </div>
                                <span class="whitespace-nowrap font-numeral-lg font-bold" dir="ltr">{{ number_format((float) $match->amount, 2) }} ₪</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @elseif ($search !== '')
                <p class="mt-4 rounded-xl bg-surface-container-low p-4 font-body-sm text-on-surface-variant">{{ __('لا توجد بيعة مطابقة. يمكنك تسجيل مرتجع بلا إيصال أدناه.') }}</p>
            @endif
        </x-card>

        @if ($transfer && ! $window['withinWindow'])
            <div class="reveal-up flex items-start gap-3 rounded-2xl border border-error/30 bg-error-container/10 p-4 text-error" style="--d:100ms">
                <span class="material-symbols-outlined flex-shrink-0">schedule</span>
                <div>
                    <p class="font-body-sm font-bold">{{ __('البيعة خارج نافذة الإرجاع') }}</p>
                    <p class="mt-1 font-body-sm">
                        {{ $windowDays > 0
                            ? __('مضى :days يومًا والنافذة :window يومًا. القرار قرارك، لكنه مسجَّل.', ['days' => $window['days'], 'window' => $windowDays])
                            : __('هذا النشاط لا يقبل المرتجعات افتراضيًا. القرار قرارك، لكنه مسجَّل.') }}
                    </p>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('returns.store') }}" class="space-y-6"
            x-data="{
                submitting: false,
                refundMethod: 'cash',
                restockingFee: 0,
                allowsRestock: {{ $allowsRestock ? 'true' : 'false' }},
                lines: {{ \Illuminate\Support\Js::from($lines->map(fn ($row) => [
                    'transferLineItemId' => $row['line']->id,
                    'name' => $row['line']->name,
                    'unitPrice' => (float) $row['line']->price,
                    'remaining' => $row['remaining'],
                    'quantity' => 0,
                    'condition' => 'resalable',
                ])->values()) }},
                lineTotal(line) { return (parseFloat(line.quantity) || 0) * line.unitPrice },
                get subtotal() { return this.lines.reduce((s, l) => s + this.lineTotal(l), 0) },
                get fee() { return Math.min(parseFloat(this.restockingFee) || 0, this.subtotal) },
                get refund() { return Math.max(this.subtotal - this.fee, 0) },
                money(v) { return (v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
            }"
            @submit="submitting = true">
            @csrf
            @if ($transfer)
                <input type="hidden" name="transferId" value="{{ $transfer->id }}" />
            @endif

            <x-card title="{{ __('بيانات المرتجع') }}">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label for="ret-customer" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اسم العميل') }}</label>
                        <input id="ret-customer" name="customerName" value="{{ old('customerName', $transfer?->customer) }}" required maxlength="255"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        @error('customerName')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="ret-phone" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الهاتف') }}</label>
                        <input id="ret-phone" name="customerPhone" value="{{ old('customerPhone') }}" dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <label for="ret-reason" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('سبب الإرجاع') }}</label>
                        <input id="ret-reason" name="reason" value="{{ old('reason') }}" maxlength="255" placeholder="{{ __('مثال: مقاس غير مناسب، عيب مصنعي') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                </div>
            </x-card>

            <x-card title="{{ __('البنود المرتجعة') }}"
                subtitle="{{ $allowsRestock
                    ? __('حدّد الكمية المرتجعة وحالتها — التالف لا يعود للرف.')
                    : __('كل مرتجع في هذا النشاط يُتلف؛ حالة البند مثبَّتة على «تالف».') }}">
                @if ($lines->isEmpty())
                    <p class="rounded-xl bg-surface-container-low p-4 font-body-sm text-on-surface-variant">
                        {{ __('اختر بيعة أولًا لتظهر بنودها.') }}
                    </p>
                @else
                    <div class="space-y-3">
                        <template x-for="(line, index) in lines" :key="index">
                            <div class="grid grid-cols-1 gap-3 rounded-2xl border border-outline-variant bg-surface-container-low/40 p-4 sm:grid-cols-12">
                                <input type="hidden" :name="'lines[' + index + '][transferLineItemId]'" :value="line.transferLineItemId" />

                                <div class="sm:col-span-5">
                                    <p class="font-body-lg font-bold" x-text="line.name"></p>
                                    <p class="font-body-sm text-outline" dir="ltr">
                                        <span x-text="@js(__('المتبقي للإرجاع')) + ': ' + line.remaining"></span>
                                    </p>
                                </div>

                                <div class="space-y-1.5 sm:col-span-3">
                                    <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" :for="'ret-qty-' + index">{{ __('الكمية') }}</label>
                                    <input :id="'ret-qty-' + index" type="number" step="0.001" min="0" :max="line.remaining"
                                        :name="'lines[' + index + '][quantity]'" x-model="line.quantity" dir="ltr"
                                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                                </div>

                                <div class="space-y-1.5 sm:col-span-2">
                                    <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" :for="'ret-cond-' + index">{{ __('الحالة') }}</label>
                                    <select :id="'ret-cond-' + index" :name="'lines[' + index + '][condition]'" x-model="line.condition" :disabled="!allowsRestock"
                                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary disabled:opacity-60">
                                        <option value="resalable">{{ __('صالح للبيع') }}</option>
                                        <option value="damaged">{{ __('تالف') }}</option>
                                    </select>
                                </div>

                                <div class="flex items-end justify-end sm:col-span-2">
                                    <p class="font-numeral-lg font-bold" dir="ltr"><span x-text="money(lineTotal(line))"></span></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    @error('lines')<p class="mt-3 font-body-sm text-error">{{ $message }}</p>@enderror
                @endif
            </x-card>

            <x-card title="{{ __('رد المبلغ') }}">
                <fieldset class="space-y-2">
                    <legend class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('طريقة الرد') }}</legend>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        @foreach (['cash' => __('نقدًا'), 'bank' => __('بنك أو محفظة'), 'store_credit' => __('رصيد لدى المحل')] as $value => $label)
                            <label class="flex cursor-pointer items-center justify-center rounded-xl border px-3 py-2.5 transition"
                                :class="refundMethod === @js($value)
                                    ? 'border-primary bg-primary text-on-primary'
                                    : 'border-outline-variant bg-surface-container-lowest text-on-surface hover:border-outline'">
                                <input type="radio" name="refundMethod" value="{{ $value }}" x-model="refundMethod" class="sr-only" />
                                <span class="font-body-sm">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5" x-show="refundMethod === 'bank'" x-cloak>
                        <label for="ret-account" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الحساب') }}</label>
                        <select id="ret-account" name="accountId"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            <option value="">{{ __('اختر') }}</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->holder_name }}</option>
                            @endforeach
                        </select>
                        @error('accountId')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="ret-fee" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('رسوم إعادة التخزين') }}</label>
                        <input id="ret-fee" type="number" step="0.01" min="0" name="restockingFee" x-model="restockingFee" dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        <p class="font-body-sm text-outline">{{ __('تُخصم من المبلغ المردود ولا تتجاوزه.') }}</p>
                    </div>
                </div>

                <dl class="mt-6 space-y-2 border-t border-outline-variant/50 pt-5">
                    <div class="flex justify-between font-body-lg">
                        <dt class="text-on-surface-variant">{{ __('قيمة البنود') }}</dt>
                        <dd class="font-numeral-lg" dir="ltr"><span x-text="money(subtotal)"></span> ₪</dd>
                    </div>
                    <div class="flex justify-between font-body-lg">
                        <dt class="text-on-surface-variant">{{ __('رسوم إعادة التخزين') }}</dt>
                        <dd class="font-numeral-lg" dir="ltr">−<span x-text="money(fee)"></span> ₪</dd>
                    </div>
                    <div class="flex justify-between border-t border-outline-variant/50 pt-2 font-headline-md text-headline-md font-bold">
                        <dt>{{ __('المبلغ المردود') }}</dt>
                        <dd class="font-numeral-lg" dir="ltr"><span x-text="money(refund)"></span> ₪</dd>
                    </div>
                </dl>

                <div class="mt-6 flex justify-end gap-3">
                    <a href="{{ route('returns.index') }}" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</a>
                    <button type="submit" :disabled="submitting || refund < 0"
                        class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                        x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ كمسودة'))">{{ __('حفظ كمسودة') }}</button>
                </div>
            </x-card>
        </form>
    </div>
@endsection
