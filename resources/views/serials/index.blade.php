@extends('layouts.app')

@section('title', __('الأرقام التسلسلية - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6">
        <header>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الأرقام التسلسلية') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('لكل وحدة هويّة: من أين جاءت، ومتى بيعت، ولمن، وهل ضمانها ساري.') }}</p>
        </header>

        <form method="GET" action="{{ route('serials.index') }}" class="reveal-up flex flex-col gap-3 sm:flex-row" style="--d:60ms">
            <x-keep-per-page />
            <div class="relative flex-1">
                <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">qr_code_scanner</span>
                <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('امسح أو اكتب الرقم التسلسلي / IMEI...') }}" aria-label="{{ __('بحث') }}" autofocus dir="ltr"
                    class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
            </div>
            <select name="status" onchange="this.form.submit()" aria-label="{{ __('الحالة') }}"
                class="rounded-2xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                <option value="">{{ __('كل الحالات') }}</option>
                @foreach ($statuses as $case)
                    <option value="{{ $case->value }}" @selected(($status ?? '') === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </form>

        @if ($mismatches->isNotEmpty())
            <div class="reveal-up rounded-2xl border border-error/30 bg-error-container/10 p-4" style="--d:100ms">
                <div class="flex items-start gap-3 text-error">
                    <span class="material-symbols-outlined flex-shrink-0">error</span>
                    <div class="min-w-0">
                        <p class="font-body-sm font-bold">{{ __('الرصيد الدفتري لا يطابق عدد الوحدات المسجَّلة') }}</p>
                        <p class="mt-1 font-body-sm">{{ __('مخزون تحرّك بلا أرقامه — كل إجابة مبنية على التسلسلي لهذه الأصناف مشكوك فيها حتى تُصحَّح.') }}</p>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($mismatches->take(10) as $row)
                        <span class="rounded-lg bg-error/10 px-2.5 py-1.5 font-body-sm text-error">
                            {{ $row['item']->name }}
                            <span dir="ltr">({{ number_format($row['ledger'], 0) }} ≠ {{ $row['serials'] }})</span>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($expiringWarranty->isNotEmpty())
            <x-card title="{{ __('ضمانات تنتهي خلال 30 يومًا') }}" subtitle="{{ __('فرصة اتصال قبل أن ينتهي الغطاء.') }}" class="reveal-up" style="--d:140ms">
                <div class="flex flex-wrap gap-2">
                    @foreach ($expiringWarranty->take(12) as $unit)
                        <a href="{{ route('serials.show', $unit->id) }}"
                            class="inline-flex items-center gap-2 rounded-xl bg-secondary-container/30 px-3 py-2 font-body-sm text-secondary transition-colors hover:bg-secondary-container/50">
                            <span dir="ltr">{{ $unit->serial }}</span>
                            <span class="opacity-70">{{ $unit->customer?->name ?? $unit->item?->name }}</span>
                            <span dir="ltr" class="font-numeral-lg">{{ $unit->warranty_ends_at?->format('Y-m-d') }}</span>
                        </a>
                    @endforeach
                </div>
            </x-card>
        @endif

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($units->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">pin</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد وحدات مسجَّلة') }}</p>
                    <p class="max-w-md font-body-sm text-outline">{{ __('تُسجَّل الأرقام تلقائيًا عند استلام أمر شراء لصنف يخضع للتتبّع التسلسلي.') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[
                    __('الرقم التسلسلي'),
                    $naming->item(),
                    __('الحالة'),
                    __('العميل'),
                    __('الضمان'),
                    __(''),
                ]">
                    <x-slot:table>
                        @foreach ($units as $unit)
                            <tr class="reveal-up transition-colors hover:bg-surface-container-low/50" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm font-bold" dir="ltr">{{ $unit->serial }}</td>
                                <td class="px-4 py-3 font-body-sm">{{ $unit->item?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 font-body-sm">
                                        <span class="material-symbols-outlined !text-lg text-on-surface-variant">{{ $unit->status->icon() }}</span>
                                        {{ $unit->status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-body-sm text-on-surface-variant">{{ $unit->customer?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="rounded-lg px-2 py-1 font-body-sm font-bold
                                        {{ $unit->underWarranty() ? 'bg-success/10 text-success' : ($unit->hasWarranty() ? 'bg-error/10 text-error' : 'bg-surface-container text-on-surface-variant') }}">
                                        {{ $unit->warrantyLabel() }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('serials.show', $unit->id) }}"
                                        class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                                        aria-label="{{ __('تتبّع الوحدة :serial', ['serial' => $unit->serial]) }}">
                                        <span class="material-symbols-outlined !text-xl rtl:-scale-x-100">chevron_right</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($units as $unit)
                            <a href="{{ route('serials.show', $unit->id) }}" class="reveal-up block rounded-2xl border border-outline-variant bg-surface-container-lowest p-4" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-body-lg font-bold" dir="ltr">{{ $unit->serial }}</p>
                                        <p class="font-body-sm text-outline">{{ $unit->item?->name }}</p>
                                    </div>
                                    <span class="whitespace-nowrap font-body-sm">{{ $unit->status->label() }}</span>
                                </div>
                            </a>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$units" :perPage="$perPage" />
            @endif
        </section>
    </div>
@endsection
