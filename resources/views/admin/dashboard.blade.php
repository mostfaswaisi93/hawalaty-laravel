@extends('layouts.admin')

@section('title', __('لوحة تحكم المنصّة — حوالاتي'))

@section('content')
    <div class="page-enter space-y-8">
        <header>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('لوحة تحكم المنصّة') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('مرحباً') }} {{ auth('admin')->user()?->name }} — {{ __('نظرة عامة على التراخيص والمحاسبة الداخلية.') }}</p>
        </header>

        {{-- License KPIs --}}
        <section class="grid grid-cols-2 gap-4 lg:grid-cols-5">
            @php
                $kpis = [
                    ['label' => __('إجمالي التراخيص'), 'value' => $totalLicenses, 'icon' => 'key', 'tone' => 'text-primary bg-primary/10'],
                    ['label' => __('مُفعّلة'), 'value' => $activeCount, 'icon' => 'check_circle', 'tone' => 'text-primary bg-primary/10'],
                    ['label' => __('تجريبية'), 'value' => $trialCount, 'icon' => 'schedule', 'tone' => 'text-secondary bg-secondary/10'],
                    ['label' => __('مُعلّقة'), 'value' => $suspendedCount, 'icon' => 'pause_circle', 'tone' => 'text-error bg-error/10'],
                    ['label' => __('منتهية'), 'value' => $expiredCount, 'icon' => 'cancel', 'tone' => 'text-outline bg-outline/10'],
                ];
            @endphp
            @foreach ($kpis as $kpi)
                <div class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full {{ $kpi['tone'] }}">
                            <span class="material-symbols-outlined">{{ $kpi['icon'] }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="font-numeral-lg text-numeral-lg font-bold">{{ number_format($kpi['value']) }}</p>
                            <p class="truncate font-body-sm text-body-sm text-on-surface-variant">{{ $kpi['label'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </section>

        {{-- Money KPIs --}}
        <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <x-card>
                <p class="font-body-sm text-on-surface-variant">{{ __('إجمالي المدفوعات') }}</p>
                <p class="mt-2 font-numeral-lg text-numeral-lg font-bold text-primary"><span dir="ltr">{{ number_format($totalPayments, 2) }} ₪</span></p>
            </x-card>
            <x-card>
                <p class="font-body-sm text-on-surface-variant">{{ __('إجمالي المصروفات') }}</p>
                <p class="mt-2 font-numeral-lg text-numeral-lg font-bold text-error"><span dir="ltr">{{ number_format($totalExpenses, 2) }} ₪</span></p>
            </x-card>
            <x-card>
                <p class="font-body-sm text-on-surface-variant">{{ __('عدد الصناديق') }}</p>
                <p class="mt-2 font-numeral-lg text-numeral-lg font-bold">{{ number_format(count($boxes)) }}</p>
            </x-card>
        </section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Recent activity --}}
            <x-card title="{{ __('آخر النشاطات') }}" subtitle="{{ __('أحدث إجراءات المشرفين') }}">
                @if ($recentActivity->isEmpty())
                    <p class="py-8 text-center font-body-sm text-on-surface-variant">{{ __('لا توجد نشاطات بعد') }}</p>
                @else
                    <ul class="divide-y divide-outline-variant/50">
                        @foreach ($recentActivity as $log)
                            <li class="flex items-center gap-3 py-3">
                                <span class="material-symbols-outlined text-outline">history</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-body-sm font-bold">{{ __($log->action) }} · {{ $log->entity_label }}</p>
                                    <p class="text-[11px] text-on-surface-variant">{{ $log->admin?->name ?? '—' }} · {{ $log->created_at?->diffForHumans() }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            {{-- Recent backups --}}
            <x-card title="{{ __('آخر النسخ الاحتياطية') }}">
                @if ($recentBackups->isEmpty())
                    <p class="py-8 text-center font-body-sm text-on-surface-variant">{{ __('لا توجد نسخ احتياطية بعد') }}</p>
                @else
                    <ul class="divide-y divide-outline-variant/50">
                        @foreach ($recentBackups as $backup)
                            <li class="flex items-center gap-3 py-3">
                                <span class="material-symbols-outlined text-outline">backup</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-body-sm font-bold">{{ $backup->license?->customer_name ?? __('قاعدة بيانات المنصّة') }}</p>
                                    <p class="text-[11px] text-on-surface-variant">{{ $backup->created_at?->diffForHumans() }} · {{ $backup->size_mb ? $backup->size_mb.' MB' : '—' }}</p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $backup->status->value === 'completed' ? 'bg-primary/10 text-primary' : ($backup->status->value === 'failed' ? 'bg-error/10 text-error' : 'bg-secondary/10 text-secondary') }}">
                                    {{ __($backup->status->value) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
@endsection
