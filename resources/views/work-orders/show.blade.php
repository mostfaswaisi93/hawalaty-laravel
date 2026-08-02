@extends('layouts.app')

@section('title', __('أمر شغل - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6"
        x-data="{
            submitting: false,
            invoicing: false,
            paymentMethod: 'cash',
            catalog: {{ \Illuminate\Support\Js::from($items->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'price' => (float) $i->price,
                'service' => $i->isService(),
            ])->values()) }},
            lines: {{ \Illuminate\Support\Js::from($order->lines->map(fn ($l) => [
                'itemId' => $l->menu_item_id,
                'quantity' => (float) $l->quantity,
                'unitPrice' => (float) $l->unit_price,
            ])->values()->all() ?: [['itemId' => '', 'quantity' => 1, 'unitPrice' => '']]) }},
            byId(id) { return this.catalog.find(c => c.id === id) || null },
            onItem(index) {
                const item = this.byId(this.lines[index].itemId);
                if (item && ! this.lines[index].unitPrice) this.lines[index].unitPrice = item.price;
            },
            add() { this.lines.push({ itemId: '', quantity: 1, unitPrice: '' }) },
            remove(i) { this.lines.splice(i, 1); if (!this.lines.length) this.add() },
            lineTotal(line) { return (parseFloat(line.quantity) || 0) * (parseFloat(line.unitPrice) || 0) },
            get parts() { return this.lines.filter(l => ! this.byId(l.itemId)?.service).reduce((s, l) => s + this.lineTotal(l), 0) },
            get labour() { return this.lines.filter(l => this.byId(l.itemId)?.service).reduce((s, l) => s + this.lineTotal(l), 0) },
            get total() { return this.parts + this.labour },
            money(v) { return (v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
        }">

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div class="min-w-0">
                <a href="{{ route('work-orders.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                    <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                    {{ __('أوامر الشغل') }}
                </a>
                <h1 class="mt-1 font-display-lg text-display-lg text-on-surface">{{ $order->customer_name }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">
                    {{ $order->subject ?: '—' }}
                    @if ($order->registration)
                        · <span dir="ltr">{{ $order->registration }}</span>
                    @endif
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span class="rounded-xl px-3 py-2 font-body-sm font-bold {{ $order->status->tone() }}">{{ $order->status->label() }}</span>

                @if (! $order->isInvoiced() && $order->status->isEditable())
                    <button type="button" @click="invoicing = true" @disabled($order->lines->isEmpty())
                        class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50">
                        <span class="material-symbols-outlined !text-xl">receipt_long</span>
                        {{ __('تسليم وفوترة') }}
                    </button>
                @endif
            </div>
        </header>

        @if ($order->isInvoiced())
            <div class="reveal-up flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-success/30 bg-success/5 p-4" style="--d:60ms">
                <div class="flex items-start gap-3 text-success">
                    <span class="material-symbols-outlined flex-shrink-0">task_alt</span>
                    <div>
                        <p class="font-body-sm font-bold">{{ __('سُلِّم وفُوتِر') }}</p>
                        <p class="mt-1 font-body-sm">{{ $order->delivered_at?->translatedFormat('j F Y - H:i') }}</p>
                        @if ($order->workmanshipStillCovered())
                            <p class="mt-1 font-body-sm">{{ __('ضمان العمل ساري حتى :date', ['date' => $order->workmanshipEndsAt()?->format('Y-m-d')]) }}</p>
                        @endif
                    </div>
                </div>
                <a href="{{ route('transfers.receipt', $order->transfer_id) }}"
                    class="rounded-xl bg-surface-container px-4 py-2 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                    {{ __('الإيصال') }}
                </a>
            </div>
        @endif

        <form method="POST" action="{{ route('work-orders.update', $order->id) }}" class="space-y-6" @submit="submitting = true">
            @csrf
            @method('PUT')

            <x-card title="{{ __('الفحص والحالة') }}">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label for="wo-status" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الحالة') }}</label>
                        <select id="wo-status" name="status" @disabled($order->isInvoiced())
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary disabled:opacity-60">
                            @foreach ($statuses as $case)
                                <option value="{{ $case->value }}" @selected($order->status === $case)>{{ $case->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label for="wo-resource" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الفني') }}</label>
                        <select id="wo-resource" name="resourceId" @disabled($order->isInvoiced())
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary disabled:opacity-60">
                            <option value="">{{ __('بدون تحديد') }}</option>
                            @foreach ($resources as $resource)
                                <option value="{{ $resource->id }}" @selected($order->service_resource_id === $resource->id)>{{ $resource->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <label for="wo-complaint" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('شكوى العميل') }}</label>
                        <textarea id="wo-complaint" name="complaint" rows="2" maxlength="2000" @disabled($order->isInvoiced())
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary disabled:opacity-60">{{ $order->complaint }}</textarea>
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <label for="wo-diagnosis" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('التشخيص') }}</label>
                        <textarea id="wo-diagnosis" name="diagnosis" rows="3" maxlength="2000" @disabled($order->isInvoiced())
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary disabled:opacity-60">{{ $order->diagnosis }}</textarea>
                    </div>
                </div>
            </x-card>

            <x-card title="{{ __('القطع وساعات العمل') }}" subtitle="{{ __('كلاهما صنف من الكتالوج — ولذلك يمكن فوترة الأمر من نفس الكاشير.') }}">
                <x-slot:actions>
                    @unless ($order->isInvoiced())
                        <button type="button" @click="add()"
                            class="flex items-center gap-1.5 rounded-xl bg-surface-container px-4 py-2 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                            <span class="material-symbols-outlined !text-lg">add</span>{{ __('بند') }}
                        </button>
                    @endunless
                </x-slot:actions>

                @if ($order->isInvoiced())
                    <x-table-responsive :headers="[__('البند'), ['label' => __('الكمية'), 'align' => 'end'], ['label' => __('السعر'), 'align' => 'end'], ['label' => __('الإجمالي'), 'align' => 'end']]">
                        <x-slot:table>
                            @foreach ($order->lines as $line)
                                <tr class="transition-colors hover:bg-surface-container-low/50">
                                    <td class="px-4 py-3">
                                        <span class="font-body-sm font-bold">{{ $line->name }}</span>
                                        <span class="ms-1 rounded bg-surface-container px-1.5 py-0.5 font-body-sm text-on-surface-variant">
                                            {{ $line->isLabour() ? __('عمل') : __('قطعة') }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ rtrim(rtrim(number_format((float) $line->quantity, 3), '0'), '.') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format((float) $line->unit_price, 3) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold" dir="ltr">{{ number_format((float) $line->line_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </x-slot:table>

                        <x-slot:cards>
                            @foreach ($order->lines as $line)
                                <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                    <p class="font-body-lg font-bold">{{ $line->name }}</p>
                                    <p class="mt-1 font-numeral-lg" dir="ltr">{{ number_format((float) $line->line_total, 2) }} ₪</p>
                                </div>
                            @endforeach
                        </x-slot:cards>
                    </x-table-responsive>
                @else
                    <div class="space-y-3">
                        <template x-for="(line, index) in lines" :key="index">
                            <div class="grid grid-cols-1 gap-3 rounded-2xl border border-outline-variant bg-surface-container-low/40 p-4 sm:grid-cols-12">
                                <div class="space-y-1.5 sm:col-span-6">
                                    <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" :for="'wol-item-' + index">{{ __('البند') }}</label>
                                    <select :id="'wol-item-' + index" :name="'lines[' + index + '][itemId]'" x-model="line.itemId" @change="onItem(index)" required
                                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                        <option value="">{{ __('اختر') }}</option>
                                        <template x-for="c in catalog" :key="c.id">
                                            <option :value="c.id" x-text="c.name + (c.service ? ' — ' + @js(__('عمل')) : '')"></option>
                                        </template>
                                    </select>
                                </div>

                                <div class="space-y-1.5 sm:col-span-2">
                                    <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" :for="'wol-qty-' + index">{{ __('الكمية') }}</label>
                                    <input :id="'wol-qty-' + index" type="number" step="0.001" min="0" :name="'lines[' + index + '][quantity]'" x-model="line.quantity" required dir="ltr"
                                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                                </div>

                                <div class="space-y-1.5 sm:col-span-2">
                                    <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" :for="'wol-price-' + index">{{ __('السعر') }}</label>
                                    <input :id="'wol-price-' + index" type="number" step="0.001" min="0" :name="'lines[' + index + '][unitPrice]'" x-model="line.unitPrice" dir="ltr"
                                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                                </div>

                                <div class="flex items-end justify-between gap-2 sm:col-span-2">
                                    <p class="font-numeral-lg font-bold" dir="ltr"><span x-text="money(lineTotal(line))"></span></p>
                                    <button type="button" @click="remove(index)"
                                        class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error"
                                        :aria-label="@js(__('حذف البند'))">
                                        <span class="material-symbols-outlined !text-xl">delete</span>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                @endif

                <dl class="mt-6 space-y-2 border-t border-outline-variant/50 pt-5">
                    <div class="flex justify-between font-body-lg">
                        <dt class="text-on-surface-variant">{{ __('القطع') }}</dt>
                        <dd class="font-numeral-lg" dir="ltr">
                            @if ($order->isInvoiced()){{ number_format((float) $order->parts_total, 2) }}@else<span x-text="money(parts)"></span>@endif ₪
                        </dd>
                    </div>
                    <div class="flex justify-between font-body-lg">
                        <dt class="text-on-surface-variant">{{ __('ساعات العمل') }}</dt>
                        <dd class="font-numeral-lg" dir="ltr">
                            @if ($order->isInvoiced()){{ number_format((float) $order->labour_total, 2) }}@else<span x-text="money(labour)"></span>@endif ₪
                        </dd>
                    </div>
                    <div class="flex justify-between border-t border-outline-variant/50 pt-2 font-headline-md text-headline-md font-bold">
                        <dt>{{ __('الإجمالي') }}</dt>
                        <dd class="font-numeral-lg" dir="ltr">
                            @if ($order->isInvoiced()){{ number_format((float) $order->total, 2) }}@else<span x-text="money(total)"></span>@endif ₪
                        </dd>
                    </div>
                </dl>

                @unless ($order->isInvoiced())
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                            x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ أمر الشغل'))">{{ __('حفظ أمر الشغل') }}</button>
                    </div>
                @endunless
            </x-card>
        </form>

        @if (! $order->isInvoiced() && $order->status->isEditable())
            <div x-show="invoicing" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="invoice-title">
                <div class="fixed inset-0 bg-black/40" x-show="invoicing" x-transition.opacity @click="invoicing = false"></div>
                <div class="relative z-10 w-full max-w-md rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                    x-show="invoicing" @keydown.escape.window="invoicing = false"
                    x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

                    <x-modal-close on-close="invoicing = false" />

                    <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
                        <h3 id="invoice-title" class="font-headline-md text-headline-md">{{ __('تسليم وفوترة') }}</h3>
                        <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">{{ __('تُنشأ فاتورة عادية: يُخصم المخزون وتُسجَّل القيود كأي بيعة.') }}</p>
                    </div>

                    <form method="POST" action="{{ route('work-orders.invoice', $order->id) }}" class="space-y-4 p-6" @submit="submitting = true">
                        @csrf

                        <fieldset class="space-y-2">
                            <legend class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('طريقة الدفع') }}</legend>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach (['cash' => __('نقدًا'), 'bank' => __('بنك أو محفظة')] as $value => $label)
                                    <label class="flex cursor-pointer items-center justify-center rounded-xl border px-3 py-2.5 transition"
                                        :class="paymentMethod === @js($value)
                                            ? 'border-primary bg-primary text-on-primary'
                                            : 'border-outline-variant bg-surface-container-lowest text-on-surface hover:border-outline'">
                                        <input type="radio" name="paymentMethod" value="{{ $value }}" x-model="paymentMethod" class="sr-only" />
                                        <span class="font-body-sm">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <div x-show="paymentMethod === 'bank'" x-cloak class="space-y-4">
                            <div class="space-y-1.5">
                                <label for="invoice-account" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الحساب') }}</label>
                                <select id="invoice-account" name="accountId"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                    <option value="">{{ __('اختر') }}</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->holder_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="space-y-1.5">
                                <label for="invoice-amount" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المبلغ المحوَّل') }}</label>
                                <input id="invoice-amount" type="number" step="0.01" min="0" name="transferredAmount" value="{{ (float) $order->total }}" dir="ltr"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            </div>

                            <div class="space-y-1.5">
                                <label for="invoice-reference" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('رقم الحوالة') }}</label>
                                <input id="invoice-reference" name="referenceNumber" maxlength="100" dir="ltr"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            </div>
                        </div>

                        <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                            <button type="button" @click="invoicing = false" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                            <button type="submit" :disabled="submitting"
                                class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                                x-text="submitting ? @js(__('جارٍ الفوترة...')) : @js(__('أصدر الفاتورة'))">{{ __('أصدر الفاتورة') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection
