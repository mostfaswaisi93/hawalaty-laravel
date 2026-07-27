@extends('layouts.app')

@section('title', $customer->name . ' - ' . __('حوالاتي'))

@section('content')
    @php
        $statusPill = fn ($status) => match ($status->value) {
            'completed' => 'bg-success-container text-success dark:bg-success-container/15',
            'pending' => 'bg-secondary-container/20 text-secondary',
            default => 'bg-error-container text-error dark:bg-error/15',
        };
        $statusLabel = fn ($status) => match ($status->value) {
            'completed' => __('مكتملة'),
            'pending' => __('قيد الانتظار'),
            default => __('فاشلة'),
        };
        // SQL aggregates from CustomerService::find(), not a sum over a
        // fully-loaded relation - see that method for why.
        $total = (float) ($customer->total_spent ?? 0);
    @endphp

    <div class="page-enter space-y-6">
        <a href="{{ route('customers.index') }}" class="inline-flex items-center gap-1 font-body-sm font-bold text-on-surface-variant transition-colors hover:text-primary" title="{{ __('العملاء') }}">
            <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
            {{ __('العملاء') }}
        </a>

        <header class="flex items-center gap-4">
            <div class="grid h-16 w-16 flex-shrink-0 place-items-center rounded-full bg-primary-container text-2xl font-bold text-on-primary-container">{{ mb_substr($customer->name, 0, 1) }}</div>
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ $customer->name }}</h1>
                @if ($customer->phone)
                    <p class="mt-1 font-body-lg text-on-surface-variant" dir="ltr">{{ $customer->phone }}</p>
                @endif
            </div>
        </header>

        <div class="stagger grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-6">
                <p class="font-body-sm text-on-surface-variant">{{ __('عدد الطلبات') }}</p>
                <p class="mt-2 font-display-lg text-3xl font-bold text-on-surface" dir="ltr">{{ number_format($customer->order_count ?? 0) }}</p>
            </div>
            <div class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-6">
                <p class="font-body-sm text-on-surface-variant">{{ __('إجمالي الإنفاق') }}</p>
                <p class="mt-2 font-display-lg text-3xl font-bold text-primary" dir="ltr">{{ number_format($total, 2) }} ₪</p>
            </div>
        </div>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            <h2 class="mb-4 font-headline-md text-headline-md text-on-surface">{{ __('سجل الطلبات') }}</h2>

            @if ($transfers->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">receipt_long</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد طلبات بعد') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[__('الصنف'), __('التاريخ'), __('الحالة'), ['label' => __('المبلغ'), 'align' => 'end'], '']">
                    <x-slot:table>
                        @foreach ($transfers as $t)
                            <tr class="reveal-up transition-colors hover:bg-surface-container-low/50" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <td class="max-w-xs truncate px-4 py-3 text-body-sm text-on-surface-variant">{{ $t->item_name ?: '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-body-sm text-on-surface-variant">{{ $t->created_at->translatedFormat('j F Y') }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-[11px] font-bold {{ $statusPill($t->status) }}">
                                        {{ $statusLabel($t->status) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-bold text-primary"><span dir="ltr">+{{ number_format($t->amount, 2) }} ₪</span></td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('transfers.receipt', $t->id) }}" target="_blank" aria-label="{{ __('طباعة الإيصال') }}"
                                        class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary">
                                        <span class="material-symbols-outlined !text-xl">print</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>
                    <x-slot:cards>
                        @foreach ($transfers as $t)
                            <div class="reveal-up tonal-elevation rounded-2xl border border-outline-variant bg-surface-container-lowest p-4"
                                style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $statusPill($t->status) }}">{{ $statusLabel($t->status) }}</span>
                                    <p class="font-bold text-primary" dir="ltr">+{{ number_format($t->amount, 2) }} ₪</p>
                                </div>
                                <p class="mt-2 truncate text-body-sm text-on-surface-variant">{{ $t->item_name ?: '-' }}</p>
                                <p class="mt-1 text-[11px] text-on-surface-variant">{{ $t->created_at->translatedFormat('j F Y') }}</p>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$transfers" :perPage="$perPage" />
            @endif
        </section>
    </div>
@endsection
