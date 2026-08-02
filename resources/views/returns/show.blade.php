@extends('layouts.app')

@section('title', __('مرتجع - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6" x-data="{ rejecting: false, submitting: false }">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div class="min-w-0">
                <a href="{{ route('returns.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                    <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                    {{ __('المرتجعات') }}
                </a>
                <h1 class="mt-1 font-display-lg text-display-lg text-on-surface">{{ $return->customer_name }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">
                    {{ $return->reference ?: mb_substr($return->id, -8) }}
                    @if ($return->reason)
                        — {{ $return->reason }}
                    @endif
                </p>
            </div>

            @if ($return->isDraft())
                <div class="flex items-center gap-2">
                    <button type="button" @click="rejecting = true"
                        class="rounded-xl bg-surface-container px-5 py-2.5 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                        {{ __('رفض') }}
                    </button>
                    <form method="POST" action="{{ route('returns.complete', $return->id) }}"
                        @submit.prevent="if (confirm('{{ __('تنفيذ المرتجع؟ سيُعاد المخزون ويُرد المبلغ، ولا يمكن التراجع.') }}')) $el.submit()">
                        @csrf
                        <button type="submit"
                            class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                            <span class="material-symbols-outlined !text-xl">check_circle</span>
                            {{ __('نفّذ المرتجع') }}
                        </button>
                    </form>
                </div>
            @endif
        </header>

        @if ($return->isCompleted())
            <div class="reveal-up flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-success/30 bg-success/5 p-4" style="--d:60ms">
                <div class="flex items-start gap-3 text-success">
                    <span class="material-symbols-outlined flex-shrink-0">task_alt</span>
                    <div>
                        <p class="font-body-sm font-bold">{{ __('نُفِّذ المرتجع') }}</p>
                        <p class="mt-1 font-body-sm">{{ $return->returned_at?->translatedFormat('j F Y - H:i') }} — {{ $return->refundMethodLabel() }}</p>
                    </div>
                </div>
                @if ($return->transfer_id)
                    <a href="{{ route('transfers.receipt', $return->transfer_id) }}"
                        class="rounded-xl bg-surface-container px-4 py-2 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                        {{ __('إيصال البيعة الأصلية') }}
                    </a>
                @endif
            </div>
        @endif

        @if ($return->isWithoutReceipt())
            <div class="reveal-up flex items-start gap-3 rounded-2xl border border-secondary-container/40 bg-secondary-container/10 p-4 text-secondary" style="--d:100ms">
                <span class="material-symbols-outlined flex-shrink-0">receipt_long</span>
                <div>
                    <p class="font-body-sm font-bold">{{ __('مرتجع بلا إيصال') }}</p>
                    <p class="mt-1 font-body-sm">{{ __('لا توجد بيعة مرتبطة — الكميات والأسعار هنا غير محقَّقة مقابل شيء.') }}</p>
                </div>
            </div>
        @endif

        <x-card title="{{ __('البنود') }}">
            <x-table-responsive :headers="[
                __('البند'),
                ['label' => __('الكمية'), 'align' => 'end'],
                ['label' => __('السعر'), 'align' => 'end'],
                ['label' => __('الإجمالي'), 'align' => 'end'],
                __('الحالة'),
            ]">
                <x-slot:table>
                    @foreach ($return->lines as $line)
                        <tr class="transition-colors hover:bg-surface-container-low/50">
                            <td class="px-4 py-3 font-body-sm font-bold">{{ $line->name }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ rtrim(rtrim(number_format((float) $line->quantity, 3), '0'), '.') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format((float) $line->unit_price, 3) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold" dir="ltr">{{ number_format((float) $line->line_total, 2) }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span class="rounded-lg px-2 py-1 font-body-sm font-bold {{ $line->isResalable() ? 'bg-success/10 text-success' : 'bg-error/10 text-error' }}">
                                    {{ $line->conditionLabel() }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:table>

                <x-slot:cards>
                    @foreach ($return->lines as $line)
                        <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                            <div class="flex items-start justify-between gap-3">
                                <p class="font-body-lg font-bold">{{ $line->name }}</p>
                                <span class="whitespace-nowrap rounded-lg px-2 py-1 font-body-sm font-bold {{ $line->isResalable() ? 'bg-success/10 text-success' : 'bg-error/10 text-error' }}">
                                    {{ $line->conditionLabel() }}
                                </span>
                            </div>
                            <p class="mt-2 font-numeral-lg font-bold" dir="ltr">{{ number_format((float) $line->line_total, 2) }} ₪</p>
                        </div>
                    @endforeach
                </x-slot:cards>
            </x-table-responsive>

            <dl class="mt-6 space-y-2 border-t border-outline-variant/50 pt-5">
                <div class="flex justify-between font-body-lg">
                    <dt class="text-on-surface-variant">{{ __('قيمة البنود') }}</dt>
                    <dd class="font-numeral-lg" dir="ltr">{{ number_format((float) $return->subtotal, 2) }} ₪</dd>
                </div>
                <div class="flex justify-between font-body-lg">
                    <dt class="text-on-surface-variant">{{ __('رسوم إعادة التخزين') }}</dt>
                    <dd class="font-numeral-lg" dir="ltr">−{{ number_format((float) $return->restocking_fee, 2) }} ₪</dd>
                </div>
                <div class="flex justify-between border-t border-outline-variant/50 pt-2 font-headline-md text-headline-md font-bold">
                    <dt>{{ __('المبلغ المردود') }}</dt>
                    <dd class="font-numeral-lg" dir="ltr">{{ number_format((float) $return->refund_total, 2) }} ₪</dd>
                </div>
            </dl>

            @unless ($allowsRestock)
                <p class="mt-4 rounded-xl bg-error-container/10 p-3 font-body-sm text-error">
                    {{ __('كل البنود مثبَّتة على «تالف»: في هذا النشاط لا يعود شيء إلى الرف بعد خروجه.') }}
                </p>
            @endunless

            @if ($return->notes)
                <p class="mt-4 rounded-xl bg-surface-container-low p-3 font-body-sm text-on-surface-variant">{{ $return->notes }}</p>
            @endif
        </x-card>

        @if ($return->isDraft())
            <div x-show="rejecting" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="reject-title">
                <div class="fixed inset-0 bg-black/40" x-show="rejecting" x-transition.opacity @click="rejecting = false"></div>
                <div class="relative z-10 w-full max-w-md rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                    x-show="rejecting" @keydown.escape.window="rejecting = false"
                    x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

                    <x-modal-close on-close="rejecting = false" />

                    <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
                        <h3 id="reject-title" class="font-headline-md text-headline-md">{{ __('رفض المرتجع') }}</h3>
                    </div>

                    <form method="POST" action="{{ route('returns.reject', $return->id) }}" class="space-y-4 p-6" @submit="submitting = true">
                        @csrf

                        <div class="space-y-1.5">
                            <label for="reject-reason" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('السبب') }}</label>
                            <textarea id="reject-reason" name="reason" rows="3" maxlength="1000" placeholder="{{ __('مثال: خارج نافذة الإرجاع') }}"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary"></textarea>
                        </div>

                        <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                            <button type="button" @click="rejecting = false" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                            <button type="submit" :disabled="submitting"
                                class="rounded-xl bg-error px-5 py-3 font-body-sm font-bold text-on-error shadow-lg transition-all hover:opacity-90 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                                x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('تأكيد الرفض'))">{{ __('تأكيد الرفض') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection
