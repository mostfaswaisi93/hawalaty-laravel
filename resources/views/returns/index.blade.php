@extends('layouts.app')

@section('title', __('المرتجعات - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('المرتجعات') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('البيعة لا تُحذف أبدًا — يُنشأ مستند عكسي مرتبط بها، ويبقى الاثنان في الدفتر.') }}</p>
            </div>
            <a href="{{ route('returns.create') }}"
                class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                <span class="material-symbols-outlined !text-xl">add</span>
                {{ __('مرتجع جديد') }}
            </a>
        </header>

        <div class="reveal-up grid grid-cols-1 gap-4 sm:grid-cols-3" style="--d:60ms">
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('مرتجعات منفَّذة') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold">{{ $summary['count'] }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('مبالغ مردودة') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold" dir="ltr">{{ number_format($summary['refunded'], 2) }} ₪</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('قيمة ما أُتلف') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold {{ $summary['destroyed'] > 0 ? 'text-error' : '' }}" dir="ltr">{{ number_format($summary['destroyed'], 2) }} ₪</p>
                <p class="mt-1 font-body-sm text-outline">{{ __('بالتكلفة — ما عاد ولم يُبَع ثانية') }}</p>
            </x-card>
        </div>

        <div class="reveal-up flex flex-col gap-3 rounded-2xl border p-4 sm:flex-row sm:items-start {{ $allowsRestock ? 'border-outline-variant bg-surface-container-low' : 'border-error/30 bg-error-container/10' }}" style="--d:100ms">
            <span class="material-symbols-outlined flex-shrink-0 {{ $allowsRestock ? 'text-on-surface-variant' : 'text-error' }}">
                {{ $allowsRestock ? 'inventory_2' : 'delete_forever' }}
            </span>
            <div class="min-w-0">
                <p class="font-body-sm font-bold {{ $allowsRestock ? 'text-on-surface' : 'text-error' }}">
                    {{ $allowsRestock ? __('المرتجع الصالح يعود إلى الرف') : __('في هذا النشاط لا يعود شيء إلى الرف') }}
                </p>
                <p class="mt-1 font-body-sm {{ $allowsRestock ? 'text-on-surface-variant' : 'text-error' }}">
                    {{ $allowsRestock
                        ? __('نافذة الإرجاع الافتراضية :days يومًا.', ['days' => $windowDays])
                        : __('الغذاء والدواء بعد خروجهما من المحل انقطعت سلسلة عهدتهما — كل مرتجع يُتلف، وليست هذه سياسة يضبطها المحل.') }}
                </p>
            </div>
        </div>

        <form method="GET" action="{{ route('returns.index') }}" class="reveal-up flex flex-col gap-3 sm:flex-row" style="--d:140ms">
            <x-keep-per-page />
            <div class="relative flex-1">
                <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">search</span>
                <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('ابحث بالرقم أو اسم العميل...') }}" aria-label="{{ __('بحث') }}"
                    class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
            </div>
            <select name="status" onchange="this.form.submit()" aria-label="{{ __('الحالة') }}"
                class="rounded-2xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                <option value="">{{ __('كل الحالات') }}</option>
                <option value="draft" @selected(($status ?? '') === 'draft')>{{ __('مسودة') }}</option>
                <option value="completed" @selected(($status ?? '') === 'completed')>{{ __('منفَّذ') }}</option>
                <option value="rejected" @selected(($status ?? '') === 'rejected')>{{ __('مرفوض') }}</option>
            </select>
        </form>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($returns->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">assignment_return</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد مرتجعات') }}</p>
                </div>
            @else
                @php
                    $tone = fn ($status) => match ($status) {
                        'completed' => 'bg-success/10 text-success',
                        'rejected' => 'bg-error/10 text-error',
                        default => 'bg-secondary-container/30 text-secondary',
                    };
                @endphp

                <x-table-responsive :headers="[
                    __('العميل'),
                    __('البيعة الأصلية'),
                    ['label' => __('البنود'), 'align' => 'end'],
                    ['label' => __('المردود'), 'align' => 'end'],
                    __('طريقة الرد'),
                    __('الحالة'),
                    __(''),
                ]">
                    <x-slot:table>
                        @foreach ($returns as $entry)
                            <tr class="reveal-up transition-colors hover:bg-surface-container-low/50" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <td class="px-4 py-3">
                                    <p class="font-body-sm font-bold">{{ $entry->customer_name }}</p>
                                    <p class="font-body-sm text-outline" dir="ltr">{{ $entry->reference ?: mb_substr($entry->id, -8) }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant" dir="ltr">
                                    @if ($entry->isWithoutReceipt())
                                        <span class="rounded-lg bg-secondary-container/40 px-2 py-1 text-secondary">{{ __('بلا إيصال') }}</span>
                                    @else
                                        {{ mb_substr($entry->transfer_id, -8) }}
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg">{{ $entry->lines->count() }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold" dir="ltr">{{ number_format((float) $entry->refund_total, 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $entry->refundMethodLabel() }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="rounded-lg px-2 py-1 font-body-sm font-bold {{ $tone($entry->status) }}">{{ $entry->statusLabel() }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('returns.show', $entry->id) }}"
                                        class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                                        aria-label="{{ __('فتح المرتجع') }}">
                                        <span class="material-symbols-outlined !text-xl rtl:-scale-x-100">chevron_right</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($returns as $entry)
                            <a href="{{ route('returns.show', $entry->id) }}" class="reveal-up block rounded-2xl border border-outline-variant bg-surface-container-lowest p-4" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-body-lg font-bold">{{ $entry->customer_name }}</p>
                                        <p class="font-body-sm text-outline">{{ $entry->refundMethodLabel() }}</p>
                                    </div>
                                    <span class="whitespace-nowrap rounded-lg px-2 py-1 font-body-sm font-bold {{ $tone($entry->status) }}">{{ $entry->statusLabel() }}</span>
                                </div>
                                <p class="mt-2 font-numeral-lg font-bold" dir="ltr">{{ number_format((float) $entry->refund_total, 2) }} ₪</p>
                            </a>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$returns" :perPage="$perPage" />
            @endif
        </section>
    </div>
@endsection
