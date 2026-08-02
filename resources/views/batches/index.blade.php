@extends('layouts.app')

@section('title', __('اللوط والصلاحية - حوالاتي'))

@php
    $badge = fn (string $status) => match ($status) {
        'expired' => 'bg-error/10 text-error',
        'soon' => 'bg-secondary-container/40 text-secondary',
        default => 'bg-success/10 text-success',
    };
@endphp

@section('content')
    <div class="page-enter space-y-6">
        <header>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('اللوط والصلاحية') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('الصرف بنظام المنتهي أولًا. البضاعة المنتهية لا تُصرف تلقائيًا أبدًا — تُتلف بقرار.') }}</p>
        </header>

        <div class="reveal-up grid grid-cols-1 gap-4 sm:grid-cols-3" style="--d:60ms">
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('لوطات على الرف') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold">{{ $rows->count() }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('قارب على الانتهاء') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold {{ $expiringSoon->isNotEmpty() ? 'text-secondary' : '' }}">{{ $expiringSoon->count() }}</p>
                <p class="mt-1 font-body-sm text-outline">{{ __('خلال :days يوم', ['days' => $soonDays]) }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('منتهٍ على الرف') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold {{ $expired->isNotEmpty() ? 'text-error' : '' }}">{{ $expired->count() }}</p>
                <p class="mt-1 font-body-sm text-outline" dir="ltr">{{ number_format($expiredValue, 2) }} ₪</p>
            </x-card>
        </div>

        @if ($expired->isNotEmpty())
            <div class="reveal-up rounded-2xl border border-error/30 bg-error-container/10 p-4" style="--d:100ms">
                <div class="flex items-start gap-3 text-error">
                    <span class="material-symbols-outlined flex-shrink-0">error</span>
                    <div class="min-w-0">
                        <p class="font-body-sm font-bold">{{ __('بضاعة منتهية الصلاحية ما زالت في المخزون') }}</p>
                        <p class="mt-1 font-body-sm">{{ __('لن تُصرف في أي بيع، لكنها تبقى محسوبة ضمن قيمة المخزون حتى تُتلف.') }}</p>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($expired->take(12) as $row)
                        <a href="{{ route('batches.show', $row['batch']->id) }}"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-error/10 px-2.5 py-1.5 font-body-sm text-error transition-colors hover:bg-error/20">
                            {{ $row['batch']->item?->name }}
                            <span dir="ltr" class="opacity-70">{{ $row['batch']->batch_number }}</span>
                            <span dir="ltr" class="font-numeral-lg font-bold">{{ number_format($row['on_hand'], 3) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <form method="GET" action="{{ route('batches.index') }}" class="reveal-up flex flex-col gap-3 sm:flex-row" style="--d:140ms">
            <div class="relative flex-1">
                <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">search</span>
                <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('ابحث برقم اللوط...') }}" aria-label="{{ __('بحث') }}"
                    class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
            </div>
            <div class="space-y-1">
                <label for="batches-days" class="sr-only">{{ __('نافذة التنبيه بالأيام') }}</label>
                <select id="batches-days" name="days" onchange="this.form.submit()"
                    class="rounded-2xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                    @foreach ([7, 14, 30, 60, 90, 180] as $option)
                        <option value="{{ $option }}" @selected($soonDays === $option)>{{ __('تنبيه قبل :days يوم', ['days' => $option]) }}</option>
                    @endforeach
                </select>
            </div>
            @if ($locations->count() > 1)
                <select name="locationId" onchange="this.form.submit()" aria-label="{{ __('الموقع') }}"
                    class="rounded-2xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                    <option value="">{{ __('كل المواقع') }}</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected($locationId === $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            @endif
        </form>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($rows->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">event_available</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد لوطات في المخزون بعد') }}</p>
                    <p class="font-body-sm text-outline">{{ __('تُفتح اللوطات تلقائيًا عند استلام أمر شراء.') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[
                    $naming->item(),
                    __('رقم اللوط'),
                    __('الصلاحية'),
                    ['label' => __('المتبقي'), 'align' => 'end'],
                    __('المورد'),
                    __('الحالة'),
                    __(''),
                ]">
                    <x-slot:table>
                        @foreach ($rows as $row)
                            @php
                                $batch = $row['batch'];
                                $unit = $batch->item?->unit() ?? \App\Enums\UnitOfMeasure::Piece;
                                $days = $batch->daysLeft();
                            @endphp
                            <tr class="reveal-up transition-colors hover:bg-surface-container-low/50" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <td class="px-4 py-3 font-body-sm font-bold">{{ $batch->item?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm" dir="ltr">{{ $batch->batch_number }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm" dir="ltr">
                                    {{ $batch->expires_at?->format('Y-m-d') ?? '—' }}
                                    @if ($days !== null)
                                        <span class="text-outline">({{ $days >= 0 ? __(':days يوم', ['days' => $days]) : __('منذ :days يوم', ['days' => abs($days)]) }})</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold" dir="ltr">{{ number_format($row['on_hand'], $unit->decimals()) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $batch->supplier?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="rounded-lg px-2 py-1 font-body-sm font-bold {{ $badge($batch->status($soonDays)) }}">{{ $batch->statusLabel($soonDays) }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('batches.show', $batch->id) }}"
                                        class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                                        aria-label="{{ __('تتبّع اللوط :batch', ['batch' => $batch->batch_number]) }}">
                                        <span class="material-symbols-outlined !text-xl rtl:-scale-x-100">chevron_right</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($rows as $row)
                            @php
                                $batch = $row['batch'];
                                $unit = $batch->item?->unit() ?? \App\Enums\UnitOfMeasure::Piece;
                            @endphp
                            <a href="{{ route('batches.show', $batch->id) }}" class="reveal-up block rounded-2xl border border-outline-variant bg-surface-container-lowest p-4" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-body-lg font-bold">{{ $batch->item?->name }}</p>
                                        <p class="font-body-sm text-outline" dir="ltr">{{ $batch->batch_number }} · {{ $batch->expires_at?->format('Y-m-d') ?? '—' }}</p>
                                    </div>
                                    <span class="whitespace-nowrap rounded-lg px-2 py-1 font-body-sm font-bold {{ $badge($batch->status($soonDays)) }}">{{ $batch->statusLabel($soonDays) }}</span>
                                </div>
                                <p class="mt-2 font-numeral-lg font-bold" dir="ltr">{{ number_format($row['on_hand'], $unit->decimals()) }} {{ $unit->shortLabel() }}</p>
                            </a>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>
            @endif
        </section>
    </div>
@endsection
