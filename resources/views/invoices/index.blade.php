@extends('layouts.app')

@section('title', __('الفوترة الإلكترونية - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الفوترة الإلكترونية') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('مستندات بترقيم متسلسل بلا فجوات، وكل واحدة مربوطة بالتي قبلها — تعديل فاتورة قديمة يكسر السلسلة كلها.') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('invoices.verify') }}"
                    class="flex items-center gap-2 rounded-xl bg-surface-container px-4 py-2.5 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                    <span class="material-symbols-outlined !text-xl">fact_check</span>
                    {{ __('تحقّق من السلسلة') }}
                </a>
                <a href="{{ route('invoices.settings') }}"
                    class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                    <span class="material-symbols-outlined !text-xl">settings</span>
                    {{ __('بيانات البائع') }}
                </a>
            </div>
        </header>

        @unless ($configured)
            <div class="reveal-up flex items-start gap-3 rounded-2xl border border-error/30 bg-error-container/10 p-4" style="--d:40ms">
                <span class="material-symbols-outlined flex-shrink-0 text-error">error</span>
                <div class="min-w-0">
                    <p class="font-body-sm font-bold text-error">{{ __('لم تُضبط بيانات البائع بعد') }}</p>
                    <p class="mt-1 font-body-sm text-error">{{ __('لا تُصدَر أي فاتورة قبل ذلك — مستند لا يقول من أصدره لا قيمة له عند أي جهة.') }}</p>
                    <a href="{{ route('invoices.settings') }}" class="mt-2 inline-block font-body-sm font-bold text-error underline">{{ __('اضبطها الآن') }}</a>
                </div>
            </div>
        @endunless

        <div class="reveal-up grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" style="--d:60ms">
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('مستندات صادرة') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold">{{ $summary['count'] }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الصافي قبل الضريبة') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold" dir="ltr">{{ number_format($summary['net'], 2) }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الضريبة المستحقة') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold" dir="ltr">{{ number_format($summary['tax'], 2) }}</p>
                <p class="mt-1 font-body-sm text-outline">{{ __('بعد خصم الإشعارات الدائنة') }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الإجمالي شامل الضريبة') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold" dir="ltr">{{ number_format($summary['gross'], 2) }}</p>
            </x-card>
        </div>

        <form method="GET" action="{{ route('invoices.index') }}" class="reveal-up flex flex-col gap-3 sm:flex-row" style="--d:100ms">
            <x-keep-per-page />
            <div class="relative flex-1">
                <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">search</span>
                <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('ابحث برقم الفاتورة أو اسم المشتري...') }}" aria-label="{{ __('بحث') }}"
                    class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
            </div>
            <select name="type" onchange="this.form.submit()" aria-label="{{ __('نوع المستند') }}"
                class="rounded-2xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                <option value="">{{ __('كل الأنواع') }}</option>
                @foreach (\App\Enums\InvoiceType::cases() as $case)
                    <option value="{{ $case->value }}" @selected(($type ?? '') === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </form>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($invoices->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">receipt_long</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد مستندات صادرة') }}</p>
                    <p class="max-w-md font-body-sm text-outline">{{ __('تُصدَر الفاتورة من صفحة البيعة، والإشعار الدائن من صفحة المرتجع بعد تنفيذه.') }}</p>
                </div>
            @else
                @php
                    $tone = fn ($invoice) => $invoice->isCreditNote() ? 'bg-error/10 text-error' : 'bg-success/10 text-success';
                @endphp

                <x-table-responsive :headers="[
                    __('الرقم'),
                    __('النوع'),
                    __('المشتري'),
                    __('التاريخ'),
                    ['label' => __('الصافي'), 'align' => 'end'],
                    ['label' => __('الضريبة'), 'align' => 'end'],
                    ['label' => __('الإجمالي'), 'align' => 'end'],
                    __(''),
                ]">
                    <x-slot:table>
                        @foreach ($invoices as $invoice)
                            <tr class="reveal-up transition-colors hover:bg-surface-container-low/50" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <td class="whitespace-nowrap px-4 py-3 font-numeral-lg font-bold" dir="ltr">{{ $invoice->number }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="rounded-lg px-2 py-1 font-body-sm font-bold {{ $tone($invoice) }}">{{ $invoice->type->label() }}</span>
                                </td>
                                <td class="px-4 py-3 font-body-sm">
                                    {{ $invoice->buyer_name ?: __('عميل نقدي') }}
                                    @if ($invoice->buyer_tax_number)
                                        <span class="block font-body-sm text-outline" dir="ltr">{{ $invoice->buyer_tax_number }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $invoice->issued_at?->translatedFormat('j M Y') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format((float) $invoice->tax_exclusive_amount, 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format((float) $invoice->tax_amount, 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold" dir="ltr">{{ number_format((float) $invoice->payable_amount, 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('invoices.show', $invoice->id) }}"
                                        class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                                        aria-label="{{ __('فتح المستند') }}">
                                        <span class="material-symbols-outlined !text-xl rtl:-scale-x-100">chevron_right</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($invoices as $invoice)
                            <a href="{{ route('invoices.show', $invoice->id) }}" class="reveal-up block rounded-2xl border border-outline-variant bg-surface-container-lowest p-4" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-numeral-lg font-bold" dir="ltr">{{ $invoice->number }}</p>
                                        <p class="font-body-sm text-outline">{{ $invoice->buyer_name ?: __('عميل نقدي') }}</p>
                                    </div>
                                    <span class="whitespace-nowrap rounded-lg px-2 py-1 font-body-sm font-bold {{ $tone($invoice) }}">{{ $invoice->type->label() }}</span>
                                </div>
                                <p class="mt-2 font-numeral-lg font-bold" dir="ltr">{{ number_format((float) $invoice->payable_amount, 2) }}</p>
                            </a>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$invoices" :perPage="$perPage" />
            @endif
        </section>
    </div>
@endsection
