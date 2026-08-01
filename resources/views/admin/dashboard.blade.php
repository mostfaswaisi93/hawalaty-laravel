@extends('layouts.admin')

@section('title', __('لوحة تحكم المنصّة - حوالاتي'))

@php
    // Neutral enum/action codes are stored in the DB and translated only here -
    // the same display-time mapping used by the tenant activity log.
    $actionLabels = [
        'created' => __('إنشاء'),
        'suspended' => __('تعليق'),
        'activated' => __('تفعيل'),
        'expired' => __('انتهاء'),
        'package_changed' => __('تغيير الباقة'),
        'logged_in' => __('تسجيل دخول'),
    ];
    $actionIcons = [
        'created' => 'add_circle',
        'suspended' => 'pause_circle',
        'activated' => 'play_circle',
        'expired' => 'event_busy',
        'package_changed' => 'swap_horiz',
        'logged_in' => 'login',
    ];
    $actionTones = [
        'created' => 'bg-primary/10 text-primary',
        'suspended' => 'bg-error/10 text-error',
        'activated' => 'bg-success-container/40 text-success dark:bg-success-container/15',
        'expired' => 'bg-outline/10 text-outline',
        'package_changed' => 'bg-secondary/10 text-secondary',
        'logged_in' => 'bg-surface-container-high text-on-surface-variant',
    ];
    $backupStatusLabels = [
        'completed' => __('مكتملة'),
        'failed' => __('فاشلة'),
        'inProgress' => __('قيد التنفيذ'),
    ];

    $trendMax = max(1, max(array_merge(array_column($trend, 'payments'), array_column($trend, 'expenses'))));
@endphp

@section('content')
    <div class="page-enter space-y-8">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('لوحة تحكم المنصّة') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('مرحباً') }} {{ auth('admin')->user()?->name }} - {{ __('نظرة عامة على التراخيص والمحاسبة الداخلية.') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.payments.index') }}"
                    class="flex items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2.5 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container" title="{{ __('تسجيل دفعة') }}">
                    <span class="material-symbols-outlined !text-xl">payments</span>{{ __('تسجيل دفعة') }}
                </a>
                <a href="{{ route('admin.licenses.index') }}"
                    class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95" title="{{ __('ترخيص جديد') }}">
                    <span class="material-symbols-outlined !text-xl">add</span>{{ __('ترخيص جديد') }}
                </a>
            </div>
        </header>

        {{-- ── Money headline ────────────────────────────────────────────── --}}
        <section class="stagger grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @php
                $moneyCards = [
                    ['label' => __('إجمالي المدفوعات'), 'value' => $totalPayments, 'icon' => 'trending_up', 'tone' => 'text-primary', 'ring' => 'bg-primary/10'],
                    ['label' => __('إجمالي المصروفات'), 'value' => $totalExpenses, 'icon' => 'trending_down', 'tone' => 'text-error', 'ring' => 'bg-error/10'],
                    ['label' => __('صافي الربح'), 'value' => $netProfit, 'icon' => 'account_balance_wallet', 'tone' => $netProfit >= 0 ? 'text-success' : 'text-error', 'ring' => $netProfit >= 0 ? 'bg-success-container/40 dark:bg-success-container/15' : 'bg-error/10'],
                    ['label' => __('رصيد الصناديق'), 'value' => $totalBoxBalance, 'icon' => 'savings', 'tone' => 'text-secondary', 'ring' => 'bg-secondary/10'],
                ];
            @endphp
            @foreach ($moneyCards as $card)
                <div class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $card['label'] }}</p>
                            <p class="mt-2 truncate font-numeral-lg text-numeral-lg font-bold {{ $card['tone'] }}" dir="ltr">{{ number_format($card['value'], 2) }} ₪</p>
                        </div>
                        <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full {{ $card['ring'] }} {{ $card['tone'] }}">
                            <span class="material-symbols-outlined">{{ $card['icon'] }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </section>

        {{-- ── License KPIs (each links to the filtered list) ─────────────── --}}
        <section class="stagger grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            @php
                $kpis = [
                    ['label' => __('إجمالي التراخيص'), 'value' => $totalLicenses, 'icon' => 'key', 'tone' => 'text-primary bg-primary/10', 'status' => null],
                    ['label' => __('مُفعّلة'), 'value' => $activeCount, 'icon' => 'check_circle', 'tone' => 'text-success bg-success-container/40 dark:bg-success-container/15', 'status' => 'active'],
                    ['label' => __('تجريبية'), 'value' => $trialCount, 'icon' => 'schedule', 'tone' => 'text-secondary bg-secondary/10', 'status' => 'trial'],
                    ['label' => __('مُعلّقة'), 'value' => $suspendedCount, 'icon' => 'pause_circle', 'tone' => 'text-error bg-error/10', 'status' => 'suspended'],
                    ['label' => __('منتهية'), 'value' => $expiredCount, 'icon' => 'cancel', 'tone' => 'text-outline bg-outline/10', 'status' => 'expired'],
                ];
            @endphp
            @foreach ($kpis as $kpi)
                <a href="{{ route('admin.licenses.index', $kpi['status'] ? ['status' => $kpi['status']] : []) }}"
                    class="tonal-elevation press group rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 transition-colors hover:border-primary">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full {{ $kpi['tone'] }}">
                            <span class="material-symbols-outlined">{{ $kpi['icon'] }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate font-numeral-lg text-numeral-lg font-bold">{{ number_format($kpi['value']) }}</p>
                            <p class="truncate font-body-sm text-body-sm text-on-surface-variant">{{ $kpi['label'] }}</p>
                        </div>
                    </div>
                </a>
            @endforeach
        </section>

        {{-- ── Expiry alerts - the only actionable block on the page ──────── --}}
        @if ($expiringSoon->isNotEmpty())
            <section class="tonal-elevation rounded-3xl border border-secondary/40 bg-secondary-container/10 p-5 md:p-6">
                <div class="mb-4 flex items-center gap-3">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-secondary/15 text-secondary">
                        <span class="material-symbols-outlined">notification_important</span>
                    </div>
                    <div>
                        <h2 class="font-headline-md text-headline-md text-on-surface">{{ __('تراخيص تنتهي قريباً') }}</h2>
                        <p class="font-body-sm text-on-surface-variant">{{ __('خلال الثلاثين يوماً القادمة - تحتاج تجديداً') }}</p>
                    </div>
                </div>
                <ul class="stagger space-y-2">
                    @foreach ($expiringSoon as $license)
                        @php $daysLeft = (int) ceil(now()->diffInDays($license->expired_at, false)); @endphp
                        <li>
                            <a href="{{ route('admin.licenses.show', $license->id) }}"
                                class="press flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-4 transition-colors hover:border-secondary">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-body-sm font-bold text-on-surface">{{ $license->customer_name }}</p>
                                    <p class="truncate text-[11px] text-on-surface-variant">
                                        <span dir="ltr">{{ $license->license_code }}</span> · {{ $license->package?->name ?? '-' }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-body-sm text-on-surface-variant">{{ $license->expired_at->translatedFormat('j M Y') }}</span>
                                    {{-- Plain conditionals rather than trans_choice: this codebase's
                                         translations are the JSON __() kind, which has no plural forms. --}}
                                    <span class="whitespace-nowrap rounded-full px-3 py-1 text-[11px] font-bold {{ $daysLeft <= 7 ? 'bg-error/10 text-error' : 'bg-secondary/15 text-secondary' }}">
                                        @if ($daysLeft <= 0)
                                            {{ __('ينتهي اليوم') }}
                                        @elseif ($daysLeft === 1)
                                            {{ __('يوم واحد') }}
                                        @else
                                            {{ $daysLeft }} {{ __('يوم') }}
                                        @endif
                                    </span>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- ── Revenue vs expenses trend ─────────────────────────────────── --}}
        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-headline-md text-headline-md text-on-surface">{{ __('الإيرادات مقابل المصروفات') }}</h2>
                    <p class="font-body-sm text-on-surface-variant">{{ __('آخر ستة أشهر') }}</p>
                </div>
                <div class="flex items-center gap-4">
                    <span class="flex items-center gap-2 font-body-sm text-on-surface-variant">
                        <span class="h-3 w-3 rounded-full bg-primary"></span>{{ __('مدفوعات') }}
                    </span>
                    <span class="flex items-center gap-2 font-body-sm text-on-surface-variant">
                        <span class="h-3 w-3 rounded-full bg-error"></span>{{ __('مصروفات') }}
                    </span>
                </div>
            </div>

            {{--
                dir="ltr" even in Arabic, so time runs OLDEST-LEFT to NEWEST-RIGHT.
                Inheriting RTL put February on the right and July on the left, which
                is not how a trend is read - and it disagreed with the activity
                heatmap, which is already pinned LTR for exactly this reason.
            --}}
            <div class="flex items-end justify-between gap-3 overflow-x-auto pb-2" dir="ltr">
                @foreach ($trend as $month)
                    @php
                        // Paired bars share one scale so the two series stay comparable.
                        // A zero is drawn as ZERO: the old code gave it 2% (~3.5px of
                        // the 176px track), a visible stub that read as "a small
                        // amount" when the truth was none at all.
                        $payHeight = $month['payments'] > 0 ? max(4, round($month['payments'] / $trendMax * 100)) : 0;
                        $expHeight = $month['expenses'] > 0 ? max(4, round($month['expenses'] / $trendMax * 100)) : 0;
                    @endphp
                    <div class="flex min-w-14 flex-1 flex-col items-center gap-2">
                        {{-- pt-6 reserves headroom for the value above the tallest bar;
                             border-b is the baseline a zero month sits flat on. --}}
                        <div class="flex h-44 w-full items-end justify-center gap-1.5 border-b border-outline-variant pt-6">
                            <div class="relative flex h-full flex-1 items-end justify-center">
                                <div class="grow-bar w-full max-w-8 rounded-t-lg bg-primary transition-all"
                                    style="height: {{ $payHeight }}%; --d: {{ $loop->index * 80 }}ms"></div>
                                {{-- Printed, not hovered: a `group-hover` tooltip is
                                     unreachable on a touch screen. --}}
                                @if ($month['payments'] > 0)
                                    <span class="pointer-events-none absolute left-1/2 -translate-x-1/2 whitespace-nowrap font-numeral-lg text-[10px] font-bold text-primary"
                                        style="bottom: calc({{ $payHeight }}% + 3px)" dir="ltr">{{ number_format($month['payments'], 0) }}</span>
                                @endif
                            </div>
                            <div class="relative flex h-full flex-1 items-end justify-center">
                                <div class="grow-bar w-full max-w-8 rounded-t-lg bg-error transition-all"
                                    style="height: {{ $expHeight }}%; --d: {{ $loop->index * 80 + 40 }}ms"></div>
                                @if ($month['expenses'] > 0)
                                    <span class="pointer-events-none absolute left-1/2 -translate-x-1/2 whitespace-nowrap font-numeral-lg text-[10px] font-bold text-error"
                                        style="bottom: calc({{ $expHeight }}% + 3px)" dir="ltr">{{ number_format($month['expenses'], 0) }}</span>
                                @endif
                            </div>
                        </div>
                        <span class="font-body-sm text-[11px] text-on-surface-variant">{{ $month['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ── Packages / boxes / partners ───────────────────────────────── --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-card title="{{ __('توزيع الباقات') }}" subtitle="{{ __('التراخيص لكل باقة') }}">
                @if (empty($packageDistribution))
                    <p class="py-8 text-center font-body-sm text-on-surface-variant">{{ __('لا توجد باقات بعد') }}</p>
                @else
                    <ul class="space-y-4">
                        @foreach ($packageDistribution as $package)
                            <li>
                                <div class="mb-1.5 flex items-center justify-between gap-2">
                                    <span class="truncate font-body-sm font-bold text-on-surface">{{ $package['name'] }}</span>
                                    <span class="flex-shrink-0 font-numeral-lg text-body-sm font-bold text-on-surface-variant">{{ $package['count'] }}</span>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-surface-container-high">
                                    <div class="grow-meter h-full rounded-full bg-primary transition-all" style="width: {{ $package['share'] }}%; --d: {{ $loop->index * 90 }}ms"></div>
                                </div>
                                <p class="mt-1 text-[11px] text-on-surface-variant">
                                    <span dir="ltr">{{ number_format($package['price'], 2) }} ₪</span> · {{ $package['share'] }}%
                                </p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="{{ __('الصناديق') }}" subtitle="{{ __('أرصدة الصناديق الحالية') }}">
                @if (empty($boxes))
                    <p class="py-8 text-center font-body-sm text-on-surface-variant">{{ __('لا توجد صناديق بعد') }}</p>
                @else
                    <ul class="stagger divide-y divide-outline-variant/50">
                        @foreach ($boxes as $box)
                            <li class="flex items-center justify-between gap-3 py-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="material-symbols-outlined flex-shrink-0 text-secondary">inbox</span>
                                    <span class="truncate font-body-sm font-bold text-on-surface">{{ $box['name'] }}</span>
                                </div>
                                <span class="flex-shrink-0 font-numeral-lg text-body-sm font-bold text-on-surface" dir="ltr">{{ number_format($box['balance'], 2) }} ₪</span>
                            </li>
                        @endforeach
                    </ul>
                    <div class="mt-3 flex items-center justify-between border-t border-outline-variant pt-3">
                        <span class="font-body-sm font-bold text-on-surface-variant">{{ __('الإجمالي') }}</span>
                        <span class="font-numeral-lg text-body-sm font-bold text-primary" dir="ltr">{{ number_format($totalBoxBalance, 2) }} ₪</span>
                    </div>
                @endif
            </x-card>

            {{--
                This figure is `box balance x percentage`, which is NOT what a
                partner is owed. A settlement pays out of DISTRIBUTABLE PROFIT
                (payments - expenses, see SettlementService), while the box
                balance also holds opening capital that was never profit - so
                the same partner legitimately shows a much larger number here
                than on the settlement screen. Both are correct about different
                things, and an admin comparing the two without being told that
                will read one of them as wrong. Hence the explicit note and the
                link, rather than a bare number.
            --}}
            <x-card title="{{ __('حصص الشركاء') }}" subtitle="{{ __('محسوبة من رصيد الصناديق') }}">
                @if (empty($partners))
                    <p class="py-8 text-center font-body-sm text-on-surface-variant">{{ __('لا يوجد شركاء بعد') }}</p>
                @else
                    <ul class="stagger divide-y divide-outline-variant/50">
                        @foreach ($partners as $partner)
                            <li class="flex items-center justify-between gap-3 py-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 font-numeral-lg text-[11px] font-bold text-primary">
                                        {{ (int) $partner['percentage'] }}%
                                    </span>
                                    <span class="truncate font-body-sm font-bold text-on-surface">{{ $partner['name'] }}</span>
                                </div>
                                <span class="flex-shrink-0 font-numeral-lg text-body-sm font-bold text-on-surface" dir="ltr">{{ number_format($partner['computedShare'], 2) }} ₪</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 flex items-start gap-1.5 border-t border-outline-variant pt-3 text-[11px] text-on-surface-variant">
                        <span class="material-symbols-outlined !text-base flex-shrink-0">info</span>
                        {{ __('حصّة من رصيد الصناديق الحالي، وليست استحقاق مقاصة - المقاصة تُوزّع الأرباح القابلة للتوزيع فقط.') }}
                    </p>
                    <a href="{{ route('admin.settlements.index') }}" class="mt-2 flex items-center justify-center gap-1 font-body-sm font-bold text-primary hover:underline" title="{{ __('عرض المقاصة') }}">
                        {{ __('عرض المقاصة') }}
                        <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_forward</span>
                    </a>
                @endif
            </x-card>
        </div>

        {{-- ── Activity + backups ────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-card title="{{ __('آخر النشاطات') }}" subtitle="{{ __('أحدث إجراءات المشرفين') }}">
                @if ($recentActivity->isEmpty())
                    <div class="reveal-scale flex flex-col items-center gap-2 py-12 text-center">
                        <span class="material-symbols-outlined !text-4xl text-outline/50">fact_check</span>
                        <p class="font-body-sm text-on-surface-variant">{{ __('لا توجد نشاطات بعد') }}</p>
                    </div>
                @else
                    <ul class="stagger divide-y divide-outline-variant/50">
                        @foreach ($recentActivity as $log)
                            <li class="flex items-center gap-3 py-3">
                                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full {{ $actionTones[$log->action] ?? 'bg-surface-container-high text-on-surface-variant' }}">
                                    <span class="material-symbols-outlined !text-xl">{{ $actionIcons[$log->action] ?? 'history' }}</span>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-body-sm font-bold text-on-surface">
                                        {{ $actionLabels[$log->action] ?? $log->action }} · {{ $log->entity_label }}
                                    </p>
                                    <p class="truncate text-[11px] text-on-surface-variant">{{ $log->admin?->name ?? '-' }} · {{ $log->created_at?->diffForHumans() }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('admin.logs.index') }}" class="mt-4 flex items-center justify-center gap-1 font-body-sm font-bold text-primary hover:underline" title="{{ __('عرض كل السجلات') }}">
                        {{ __('عرض كل السجلات') }}
                        <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_forward</span>
                    </a>
                @endif
            </x-card>

            <x-card title="{{ __('آخر النسخ الاحتياطية') }}" subtitle="{{ __('نسبة النجاح') }}: {{ $backupStats['successRate'] }}%">
                @if ($recentBackups->isEmpty())
                    <div class="reveal-scale flex flex-col items-center gap-2 py-12 text-center">
                        <span class="material-symbols-outlined !text-4xl text-outline/50">backup</span>
                        <p class="font-body-sm text-on-surface-variant">{{ __('لا توجد نسخ احتياطية بعد') }}</p>
                    </div>
                @else
                    <div class="mb-4 flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full bg-success"></span>
                            <span class="font-body-sm text-on-surface-variant">{{ __('مكتملة') }}: <span class="font-bold text-on-surface">{{ $backupStats['completed'] }}</span></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full bg-error"></span>
                            <span class="font-body-sm text-on-surface-variant">{{ __('فاشلة') }}: <span class="font-bold text-on-surface">{{ $backupStats['failed'] }}</span></span>
                        </div>
                    </div>
                    <ul class="stagger divide-y divide-outline-variant/50">
                        @foreach ($recentBackups as $backup)
                            <li class="flex items-center gap-3 py-3">
                                <span class="material-symbols-outlined flex-shrink-0 text-outline">backup</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-body-sm font-bold text-on-surface">{{ $backup->license?->customer_name ?? __('قاعدة بيانات المنصّة') }}</p>
                                    <p class="truncate text-[11px] text-on-surface-variant">{{ $backup->created_at?->diffForHumans() }} · {{ $backup->size_mb ? $backup->size_mb.' MB' : '-' }}</p>
                                </div>
                                <span class="flex-shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $backup->status->value === 'completed' ? 'bg-success-container/40 text-success dark:bg-success-container/15' : ($backup->status->value === 'failed' ? 'bg-error/10 text-error' : 'bg-secondary/10 text-secondary') }}">
                                    {{ $backupStatusLabels[$backup->status->value] ?? $backup->status->value }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('admin.backups.index') }}" class="mt-4 flex items-center justify-center gap-1 font-body-sm font-bold text-primary hover:underline" title="{{ __('إدارة النسخ الاحتياطية') }}">
                        {{ __('إدارة النسخ الاحتياطية') }}
                        <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_forward</span>
                    </a>
                @endif
            </x-card>
        </div>
    </div>
@endsection
