@extends('layouts.app')

@section('title', __('مستند :number - حوالاتي', ['number' => $invoice->number]))

@section('content')
    <div class="page-enter space-y-6">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div class="min-w-0">
                <a href="{{ route('invoices.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                    <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                    {{ __('الفوترة الإلكترونية') }}
                </a>
                <h1 class="mt-1 font-display-lg text-display-lg text-on-surface" dir="ltr">{{ $invoice->number }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">
                    {{ $invoice->type->label() }} — {{ $invoice->issued_at?->translatedFormat('j F Y - H:i') }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                @if ($invoice->transfer_id)
                    <a href="{{ route('transfers.receipt', $invoice->transfer_id) }}"
                        class="rounded-xl bg-surface-container px-4 py-2.5 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                        {{ __('الإيصال') }}
                    </a>
                @endif
                <a href="{{ route('invoices.xml', $invoice->id) }}"
                    class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                    <span class="material-symbols-outlined !text-xl">download</span>
                    {{ __('تنزيل UBL') }}
                </a>
            </div>
        </header>

        @unless ($invoice->totalsBalance())
            <div class="reveal-up flex items-start gap-3 rounded-2xl border border-error/30 bg-error-container/10 p-4" style="--d:40ms">
                <span class="material-symbols-outlined flex-shrink-0 text-error">error</span>
                <div>
                    <p class="font-body-sm font-bold text-error">{{ __('حساب المستند لا يتوازن') }}</p>
                    <p class="mt-1 font-body-sm text-error">{{ __('مجاميع لا تنطبق على بعضها تُرفَض عند أي إرسال — راجعها قبل استخدام هذا المستند.') }}</p>
                </div>
            </div>
        @endunless

        <div class="reveal-up grid grid-cols-1 gap-4 lg:grid-cols-2" style="--d:60ms">
            <x-card title="{{ __('البائع') }}">
                <p class="font-body-lg font-bold">{{ $invoice->seller_name }}</p>
                @if ($invoice->seller_tax_number)
                    <p class="mt-1 font-body-sm text-on-surface-variant" dir="ltr">{{ __('الرقم الضريبي') }}: {{ $invoice->seller_tax_number }}</p>
                @endif
                @if ($invoice->seller_address)
                    <p class="mt-1 font-body-sm text-on-surface-variant">{{ $invoice->seller_address }}</p>
                @endif
                <p class="mt-3 font-body-sm text-outline">{{ __('مثبَّتة لحظة الإصدار — تغيير بيانات المحل لاحقًا لا يمسّ ما صدر.') }}</p>
            </x-card>

            <x-card title="{{ __('المشتري') }}">
                <p class="font-body-lg font-bold">{{ $invoice->buyer_name ?: __('عميل نقدي') }}</p>
                @if ($invoice->buyer_tax_number)
                    <p class="mt-1 font-body-sm text-on-surface-variant" dir="ltr">{{ __('الرقم الضريبي') }}: {{ $invoice->buyer_tax_number }}</p>
                @endif
                @if ($invoice->buyer_address)
                    <p class="mt-1 font-body-sm text-on-surface-variant">{{ $invoice->buyer_address }}</p>
                @endif
                @if ($invoice->type->isSimplified())
                    <p class="mt-3 font-body-sm text-outline">{{ __('فاتورة مبسّطة: بيع مباشر للمستهلك، ولا يلزمها رقم ضريبي للمشتري.') }}</p>
                @endif
            </x-card>
        </div>

        <x-card title="{{ __('البنود') }}">
            <x-table-responsive :headers="[
                '#',
                __('البند'),
                ['label' => __('الكمية'), 'align' => 'end'],
                ['label' => __('سعر الوحدة'), 'align' => 'end'],
                ['label' => __('الصافي'), 'align' => 'end'],
                __('الفئة'),
                ['label' => __('الضريبة'), 'align' => 'end'],
                ['label' => __('الإجمالي'), 'align' => 'end'],
            ]">
                <x-slot:table>
                    @foreach ($invoice->lines as $line)
                        <tr class="transition-colors hover:bg-surface-container-low/50">
                            <td class="px-4 py-3 font-numeral-lg text-outline">{{ $line->position }}</td>
                            <td class="px-4 py-3 font-body-sm font-bold">{{ $line->name }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">
                                {{ rtrim(rtrim(number_format((float) $line->quantity, 3), '0'), '.') }}
                                <span class="text-outline">{{ $line->unit()->shortLabel() }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format((float) $line->unit_price, 3) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format((float) $line->line_extension_amount, 2) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">
                                {{ $line->category()->label() }}
                                <span class="text-outline" dir="ltr">{{ rtrim(rtrim(number_format((float) $line->tax_rate, 2), '0'), '.') }}%</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format((float) $line->tax_amount, 2) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold" dir="ltr">{{ number_format($line->grossAmount(), 2) }}</td>
                        </tr>
                    @endforeach
                </x-slot:table>

                <x-slot:cards>
                    @foreach ($invoice->lines as $line)
                        <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                            <div class="flex items-start justify-between gap-3">
                                <p class="font-body-lg font-bold">{{ $line->name }}</p>
                                <span class="whitespace-nowrap font-body-sm text-outline">{{ $line->category()->label() }}</span>
                            </div>
                            <p class="mt-1 font-body-sm text-on-surface-variant" dir="ltr">
                                {{ rtrim(rtrim(number_format((float) $line->quantity, 3), '0'), '.') }} × {{ number_format((float) $line->unit_price, 3) }}
                            </p>
                            <p class="mt-2 font-numeral-lg font-bold" dir="ltr">{{ number_format($line->grossAmount(), 2) }}</p>
                        </div>
                    @endforeach
                </x-slot:cards>
            </x-table-responsive>

            <dl class="mt-6 space-y-2 border-t border-outline-variant/50 pt-5">
                <div class="flex justify-between font-body-lg">
                    <dt class="text-on-surface-variant">{{ __('مجموع البنود قبل الضريبة') }}</dt>
                    <dd class="font-numeral-lg" dir="ltr">{{ number_format((float) $invoice->line_extension_amount, 2) }}</dd>
                </div>
                @if ((float) $invoice->allowance_total > 0)
                    <div class="flex justify-between font-body-lg">
                        <dt class="text-on-surface-variant">{{ __('خصم على مستوى المستند') }}</dt>
                        <dd class="font-numeral-lg" dir="ltr">−{{ number_format((float) $invoice->allowance_total, 2) }}</dd>
                    </div>
                @endif
                <div class="flex justify-between font-body-lg">
                    <dt class="text-on-surface-variant">{{ __('الوعاء الخاضع للضريبة') }}</dt>
                    <dd class="font-numeral-lg" dir="ltr">{{ number_format((float) $invoice->tax_exclusive_amount, 2) }}</dd>
                </div>
                <div class="flex justify-between font-body-lg">
                    <dt class="text-on-surface-variant">{{ __('الضريبة') }}</dt>
                    <dd class="font-numeral-lg" dir="ltr">{{ number_format((float) $invoice->tax_amount, 2) }}</dd>
                </div>
                <div class="flex justify-between border-t border-outline-variant/50 pt-2 font-headline-md text-headline-md font-bold">
                    <dt>{{ __('المستحق') }}</dt>
                    <dd class="font-numeral-lg" dir="ltr">{{ number_format((float) $invoice->payable_amount, 2) }} {{ $invoice->currency_code }}</dd>
                </div>
            </dl>
        </x-card>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <x-card title="{{ __('تفصيل الضريبة') }}">
                <p class="mb-4 font-body-sm text-on-surface-variant">{{ __('مجموعة واحدة لكل فئة ونسبة، لا سطر لكل بند — هكذا يطلبها المعيار.') }}</p>

                <div class="space-y-2">
                    @foreach ($invoice->taxBreakdown() as $row)
                        @php $category = \App\Enums\TaxCategory::tryFrom($row['category']) ?? \App\Enums\TaxCategory::Standard; @endphp
                        <div class="rounded-xl border border-outline-variant/60 bg-surface-container-low p-3">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-body-sm font-bold">
                                    {{ $category->label() }}
                                    <span class="text-outline" dir="ltr">({{ $category->value }} — {{ rtrim(rtrim(number_format($row['rate'], 2), '0'), '.') }}%)</span>
                                </p>
                                <p class="whitespace-nowrap font-numeral-lg font-bold" dir="ltr">{{ number_format($row['tax'], 2) }}</p>
                            </div>
                            <p class="mt-1 font-body-sm text-on-surface-variant" dir="ltr">
                                {{ __('الوعاء') }}: {{ number_format($row['taxable'], 2) }}
                            </p>
                            @if ($category->exemptionReason())
                                <p class="mt-1 font-body-sm text-outline">{{ $category->exemptionReason() }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-card>

            <x-card title="{{ __('التوقيع والسلسلة') }}">
                <dl class="space-y-3">
                    <div>
                        <dt class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('بصمة هذا المستند') }}</dt>
                        <dd class="mt-1 break-all rounded-lg bg-surface-container-low p-2 font-mono text-body-sm" dir="ltr">{{ $invoice->hash }}</dd>
                    </div>
                    <div>
                        <dt class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('بصمة ما قبله') }}</dt>
                        <dd class="mt-1 break-all rounded-lg bg-surface-container-low p-2 font-mono text-body-sm" dir="ltr">
                            {{ $invoice->previous_hash ?: __('أول مستند في السلسلة') }}
                        </dd>
                    </div>
                    @if ($invoice->qr_payload)
                        <div>
                            <dt class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('حمولة رمز QR') }}</dt>
                            <dd class="mt-1 break-all rounded-lg bg-surface-container-low p-2 font-mono text-body-sm" dir="ltr">{{ $invoice->qr_payload }}</dd>
                            @if ($qr !== [])
                                <ul class="mt-2 space-y-1">
                                    @foreach ($qr as $tag => $value)
                                        <li class="font-body-sm text-on-surface-variant"><span class="text-outline" dir="ltr">[{{ $tag }}]</span> {{ $value }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif
                </dl>

                <p class="mt-4 rounded-xl bg-surface-container-low p-3 font-body-sm text-on-surface-variant">
                    {{ __('هذا المستند غير موقَّع بشهادة جهة ضريبية ولم يُرسَل لأي منظومة إقرار — تلك خطوة تختلف من دولة لأخرى وتحتاج اعتمادات لا يحملها هذا النظام. ما هنا هو المستند الصحيح الذي يُدخَل إليها.') }}
                </p>
            </x-card>
        </div>
    </div>
@endsection
