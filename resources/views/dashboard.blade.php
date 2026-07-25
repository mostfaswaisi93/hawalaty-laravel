@extends('layouts.app')

@section('title', __('لوحة التحكم - حوالاتي'))

@section('content')
    @php
        $member = auth('tenant')->user();
        $maxDaily = max(1, ...array_map(fn ($d) => $d['count'], $weeklyActivity));
        $weeklyTotalCount = array_sum(array_map(fn ($d) => $d['count'], $weeklyActivity));
        $dayLabels = [__('أحد'), __('اثنين'), __('ثلاثاء'), __('أربعاء'), __('خميس'), __('جمعة'), __('سبت')];

        $statusPill = fn ($status) => match ($status->value) {
            'completed' => 'bg-success-container text-success dark:bg-success-container/15',
            'pending' => 'bg-secondary-container/20 text-secondary',
            default => 'bg-error-container text-error dark:bg-error/15',
        };
        $statusDot = fn ($status) => match ($status->value) {
            'completed' => 'bg-green-500',
            'pending' => 'bg-secondary-container',
            default => 'bg-error',
        };
        $statusLabel = fn ($status) => match ($status->value) {
            'completed' => __('مكتملة'),
            'pending' => __('قيد الانتظار'),
            default => __('فاشلة'),
        };
    @endphp

    <div class="page-enter space-y-8">
        <!-- Hero: revenue + quick actions -->
        <section class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <div class="group relative flex min-h-[240px] flex-col justify-between overflow-hidden rounded-3xl bg-gradient-to-br from-[#00236f] to-[#1e3a8a] p-8 text-white shadow-xl lg:col-span-2">
                <div class="relative z-10 flex items-start justify-between">
                    <div>
                        <p class="mb-2 font-body-sm text-body-sm text-white/80">{{ __('إجمالي الإيرادات') }}</p>
                        <h2 class="font-display-lg text-4xl leading-none tracking-tight sm:text-[48px]" dir="ltr">{{ number_format($totalRevenue, 2) }} ₪</h2>
                    </div>
                    <span class="material-symbols-outlined !text-4xl opacity-30 transition-opacity group-hover:opacity-60">point_of_sale</span>
                </div>

                <div class="relative z-10 flex items-end justify-between">
                    <div class="space-y-1">
                        <p class="font-label-caps text-[10px] uppercase tracking-widest opacity-60">{{ __('إجمالي الطلبات') }}</p>
                        <p class="font-numeral-lg text-lg tracking-widest" dir="ltr">{{ $orderCount }}</p>
                    </div>
                    <a href="/history" class="rounded-lg bg-white/10 px-6 py-2 text-sm font-bold backdrop-blur-md transition-all hover:bg-white/20">
                        {{ __('عرض السجل') }}
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                @if ($member?->hasPermission(\App\Enums\PermissionKey::TransfersCreate))
                    <a href="/transfers" class="tonal-elevation group flex flex-col items-center justify-center gap-3 rounded-2xl border border-outline-variant bg-surface-container-lowest p-6 transition-all hover:border-primary hover:bg-surface-container">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-secondary-container/10 text-secondary transition-colors group-hover:bg-secondary-container group-hover:text-on-secondary-fixed">
                            <span class="material-symbols-outlined">add</span>
                        </div>
                        <span class="text-body-sm font-bold">{{ __('طلب جديد') }}</span>
                    </a>
                @endif

                @if ($member?->hasPermission(\App\Enums\PermissionKey::MenuManage))
                    <a href="/menu" class="tonal-elevation group flex flex-col items-center justify-center gap-3 rounded-2xl border border-outline-variant bg-surface-container-lowest p-6 transition-all hover:border-primary hover:bg-surface-container">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-on-primary">
                            <span class="material-symbols-outlined">restaurant_menu</span>
                        </div>
                        <span class="text-body-sm font-bold">{{ __('قائمة الطعام') }}</span>
                    </a>
                @endif

                @if ($member?->hasPermission(\App\Enums\PermissionKey::ExpensesManage))
                    <a href="/expenses" class="tonal-elevation group flex flex-col items-center justify-center gap-3 rounded-2xl border border-outline-variant bg-surface-container-lowest p-6 transition-all hover:border-primary hover:bg-surface-container">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-tertiary/10 text-tertiary transition-colors group-hover:bg-tertiary group-hover:text-on-tertiary">
                            <span class="material-symbols-outlined">payments</span>
                        </div>
                        <span class="text-body-sm font-bold">{{ __('المصروفات') }}</span>
                    </a>
                @endif

                <a href="/reports" class="tonal-elevation group flex flex-col items-center justify-center gap-3 rounded-2xl border border-outline-variant bg-surface-container-lowest p-6 transition-all hover:border-primary hover:bg-surface-container">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-surface-dim/30 text-on-surface-variant transition-colors group-hover:bg-primary-container group-hover:text-on-primary-container">
                        <span class="material-symbols-outlined">assessment</span>
                    </div>
                    <span class="text-body-sm font-bold">{{ __('التقارير') }}</span>
                </a>
            </div>
        </section>

        <!-- Weekly activity -->
        {{-- Fixed dark navy (not the bg-inverse-surface token, which flips to
             near-white in dark mode) so this stays visually paired with the
             hero card above it — which is also a fixed dark gradient —
             instead of the two swapping contrast against each other. --}}
        <section class="tonal-elevation relative overflow-hidden rounded-3xl bg-[rgb(33,49,69)] p-6 text-[rgb(234,241,255)]">
            <div class="relative z-10 flex h-full flex-col">
                <div class="mb-8 flex items-center justify-between">
                    <h3 class="font-headline-md text-headline-md">{{ __('نشاط الأسبوع') }}</h3>
                    <span class="material-symbols-outlined text-secondary-container">trending_up</span>
                </div>
                <div>
                    <p class="mb-1 text-sm opacity-70">{{ __('إجمالي طلبات هذا الأسبوع') }}</p>
                    <h4 class="mb-4 text-3xl font-bold" dir="ltr">{{ $weeklyTotalCount }}</h4>
                    <div class="mt-4 flex h-24 items-end gap-2">
                        @foreach ($weeklyActivity as $day)
                            <div class="flex h-full w-full flex-col items-center justify-end gap-1">
                                <span class="font-numeral-lg text-[10px] opacity-70" dir="ltr">{{ $day['count'] }}</span>
                                <div
                                    class="w-full rounded-t-lg transition-all duration-500 {{ $day['count'] === $maxDaily ? 'bg-secondary-container' : 'bg-primary-container opacity-40' }}"
                                    style="height: {{ max(4, ($day['count'] / $maxDaily) * 100) }}%"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 flex justify-between font-label-caps text-[10px] opacity-50">
                        @foreach ($weeklyActivity as $day)
                            <span>{{ $dayLabels[\Illuminate\Support\Carbon::parse($day['date'])->dayOfWeek] }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="pointer-events-none absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 2px 2px, rgb(234,241,255) 1px, transparent 0); background-size: 24px 24px;"></div>
        </section>

        <!-- Recent orders -->
        <section class="tonal-elevation rounded-3xl border border-outline-variant/30 bg-surface-container-lowest p-8">
            <div class="mb-8 flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
                <div>
                    <h3 class="font-headline-md text-headline-md">{{ __('أحدث الطلبات') }}</h3>
                    <p class="text-body-sm text-outline">{{ __('تتبّع أحدث طلبات المطعم في مكان واحد') }}</p>
                </div>
                <a href="/history" class="flex items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-low px-4 py-2 text-body-sm font-bold transition-colors hover:bg-surface-container-high">
                    <span class="material-symbols-outlined !text-lg">receipt_long</span>
                    {{ __('عرض كل الطلبات') }}
                </a>
            </div>

            @if ($recentTransfers->isEmpty())
                <div class="flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">receipt_long</span>
                    <p class="text-on-surface-variant">{{ __('لا توجد طلبات بعد') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[__('العميل'), __('الصنف'), __('التاريخ'), __('الحالة'), __('المبلغ')]">
                    <x-slot:table>
                        @foreach ($recentTransfers as $t)
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                            <span class="material-symbols-outlined !text-xl">{{ $t->payment_method->value === 'cash' ? 'payments' : 'account_balance' }}</span>
                                        </div>
                                        <div>
                                            <p class="whitespace-nowrap text-body-sm font-bold">{{ $t->customer }}</p>
                                            <p class="whitespace-nowrap text-[11px] text-on-surface-variant">{{ $t->payment_method->value === 'cash' ? __('نقدي') : $t->bank?->name }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-body-sm text-on-surface-variant">{{ $t->item_name ?: '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-body-sm text-on-surface-variant">{{ $t->created_at->translatedFormat('j F Y') }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-[11px] font-bold {{ $statusPill($t->status) }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $statusDot($t->status) }}"></span>
                                        {{ $statusLabel($t->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-start font-bold text-primary">
                                    <span dir="ltr">+{{ number_format($t->amount, 2) }} ₪</span>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>
                    <x-slot:cards>
                        @foreach ($recentTransfers as $t)
                            <div class="tonal-elevation rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                        <span class="material-symbols-outlined !text-xl">{{ $t->payment_method->value === 'cash' ? 'payments' : 'account_balance' }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-body-sm font-bold">{{ $t->customer }}</p>
                                        <p class="truncate text-[11px] text-on-surface-variant">{{ $t->payment_method->value === 'cash' ? __('نقدي') : $t->bank?->name }}</p>
                                    </div>
                                    <p class="whitespace-nowrap font-bold text-primary"><span dir="ltr">+{{ number_format($t->amount, 2) }} ₪</span></p>
                                </div>
                                <div class="mt-2.5 flex items-center justify-between gap-2 ps-[52px]">
                                    <span class="truncate text-[11px] text-on-surface-variant">{{ $t->item_name ?: '—' }} · {{ $t->created_at->translatedFormat('j M') }}</span>
                                    <span class="inline-flex flex-shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $statusPill($t->status) }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $statusDot($t->status) }}"></span>
                                        {{ $statusLabel($t->status) }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>
            @endif
        </section>
    </div>
@endsection
