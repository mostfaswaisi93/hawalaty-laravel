@extends('layouts.app')

@section('title', __('سجل النشاطات — حوالاتي'))

@section('content')
    @php
        $actionLabels = ['created' => __('إنشاء'), 'updated' => __('تعديل'), 'deleted' => __('حذف'), 'loggedIn' => __('تسجيل دخول'), 'signedOut' => __('تسجيل خروج')];
        $categoryLabels = ['auth' => __('المصادقة'), 'team' => __('الفريق'), 'banks' => __('البنوك'), 'menu' => __('القائمة'), 'expenses' => __('المصروفات'), 'constants' => __('الثوابت'), 'devices' => __('الأجهزة'), 'transfers' => __('الحوالات')];
        $entityLabels = ['expense' => __('مصروف'), 'bank' => __('بنك'), 'menuItem' => __('عنصر قائمة'), 'constant' => __('ثابت'), 'device' => __('جهاز'), 'order' => __('حوالة'), 'teamMember' => __('عضو فريق'), 'role' => __('دور')];
        $actionPill = fn ($v) => match ($v) {
            'created' => 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300',
            'deleted' => 'bg-error-container text-error dark:bg-error/15',
            'updated' => 'bg-secondary-container/20 text-secondary',
            default => 'bg-surface-container-high text-on-surface-variant',
        };
        $hasFilters = ! empty($filters);
    @endphp

    <div class="page-enter space-y-6">
        <header>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('سجل النشاطات') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('كل الإجراءات التي تمّت في مساحة عملك.') }}</p>
        </header>

        <form method="GET" action="{{ route('activity-log.index') }}" class="tonal-elevation grid grid-cols-1 gap-4 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 sm:grid-cols-3">
            <div class="space-y-1.5 sm:col-span-1">
                <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('بحث') }}</label>
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-3' : 'left-0 pl-3' }}">search</span>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('المنفّذ أو العنصر...') }}"
                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-10 pl-3' : 'pl-10 pr-3' }}" />
                </div>
            </div>
            <div class="space-y-1.5">
                <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الفئة') }}</label>
                <select name="category" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                    <option value="">{{ __('الكل') }}</option>
                    @foreach (\App\Enums\AuditCategory::cases() as $cat)
                        <option value="{{ $cat->value }}" @selected(($filters['category'] ?? '') === $cat->value)>{{ $categoryLabels[$cat->value] ?? $cat->value }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex items-center gap-2 rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                    <span class="material-symbols-outlined !text-xl">filter_alt</span> {{ __('تطبيق') }}
                </button>
                @if ($hasFilters)
                    <a href="{{ route('activity-log.index') }}" aria-label="{{ __('مسح الفلاتر') }}" class="flex items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">
                        <span class="material-symbols-outlined !text-xl">close</span>
                    </a>
                @endif
            </div>
        </form>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($logs->isEmpty())
                <div class="flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">{{ $hasFilters ? 'search_off' : 'fact_check' }}</span>
                    <p class="font-body-lg text-on-surface-variant">{{ $hasFilters ? __('لا نتائج مطابقة') : __('لا نشاط مسجّل بعد') }}</p>
                </div>
            @else
                <ul class="divide-y divide-outline-variant/50">
                    @foreach ($logs as $log)
                        <li class="flex items-start gap-4 py-4">
                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-surface-container-high text-on-surface-variant">
                                <span class="material-symbols-outlined !text-xl">{{ $log->action->value === 'deleted' ? 'delete' : ($log->action->value === 'created' ? 'add_circle' : 'edit') }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $actionPill($log->action->value) }}">{{ $actionLabels[$log->action->value] ?? $log->action->value }}</span>
                                    <span class="font-body-sm text-on-surface-variant">{{ $entityLabels[$log->entity_type->value] ?? $log->entity_type->value }}</span>
                                    <span class="font-body-sm font-bold text-on-surface">{{ $log->entity_label }}</span>
                                </div>
                                <p class="mt-1 text-[12px] text-outline">
                                    {{ __('بواسطة') }} {{ $log->actor_name ?: __('النظام') }}
                                    · {{ $categoryLabels[$log->category->value] ?? $log->category->value }}
                                </p>
                            </div>
                            <span class="whitespace-nowrap text-[11px] text-outline">{{ $log->created_at->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ul>

                @if ($logs->hasPages())
                    <div class="mt-6 flex items-center justify-between gap-4 border-t border-outline-variant/50 pt-5">
                        <span class="font-body-sm text-on-surface-variant">{{ __('صفحة') }} {{ $logs->currentPage() }} {{ __('من') }} {{ $logs->lastPage() }}</span>
                        <div class="flex items-center gap-2">
                            @if ($logs->onFirstPage())
                                <span class="rounded-xl border border-outline-variant/60 px-4 py-2 font-body-sm text-outline/60">{{ __('السابق') }}</span>
                            @else
                                <a href="{{ $logs->previousPageUrl() }}" class="rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container">{{ __('السابق') }}</a>
                            @endif
                            @if ($logs->hasMorePages())
                                <a href="{{ $logs->nextPageUrl() }}" class="rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container">{{ __('التالي') }}</a>
                            @else
                                <span class="rounded-xl border border-outline-variant/60 px-4 py-2 font-body-sm text-outline/60">{{ __('التالي') }}</span>
                            @endif
                        </div>
                    </div>
                @endif
            @endif
        </section>
    </div>
@endsection
