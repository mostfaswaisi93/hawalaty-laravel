@extends('layouts.app')

@section('title', __('سجل النشاطات - حوالاتي'))

@section('content')
    @php
        $actionLabels = ['created' => __('إنشاء'), 'updated' => __('تعديل'), 'deleted' => __('حذف'), 'loggedIn' => __('تسجيل دخول'), 'signedOut' => __('تسجيل خروج')];
        $categoryLabels = ['auth' => __('المصادقة'), 'team' => __('الفريق'), 'banks' => __('البنوك'), 'menu' => __('القائمة'), 'expenses' => __('المصروفات'), 'constants' => __('الثوابت'), 'devices' => __('الأجهزة'), 'transfers' => __('الحوالات')];
        $entityLabels = ['expense' => __('مصروف'), 'bank' => __('بنك'), 'menuItem' => __('عنصر قائمة'), 'constant' => __('ثابت'), 'device' => __('جهاز'), 'order' => __('حوالة'), 'teamMember' => __('عضو فريق'), 'role' => __('دور')];
        $actionPill = fn ($v) => match ($v) {
            'created' => 'bg-success-container text-success dark:bg-success-container/15',
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

        <form method="GET" action="{{ route('activity-log.index') }}" x-data="{ submitting: false }" @submit="submitting = true" class="reveal-up tonal-elevation grid grid-cols-1 gap-4 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 sm:grid-cols-3" style="--d: 40ms">
            {{-- Filtering must not throw away a chosen page size. --}}
            <x-keep-per-page />
            <div class="space-y-1.5 sm:col-span-1">
                <label for="activity-log-search" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('بحث') }}</label>
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-3' : 'left-0 pl-3' }}">search</span>
                    <input id="activity-log-search" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('المنفّذ أو العنصر...') }}"
                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-10 pl-3' : 'pl-10 pr-3' }}" />
                </div>
            </div>
            <div class="space-y-1.5">
                <label for="activity-log-category" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الفئة') }}</label>
                <select id="activity-log-category" name="category" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                    <option value="">{{ __('الكل') }}</option>
                    @foreach (\App\Enums\AuditCategory::cases() as $cat)
                        <option value="{{ $cat->value }}" @selected(($filters['category'] ?? '') === $cat->value)>{{ $categoryLabels[$cat->value] ?? $cat->value }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" :disabled="submitting" class="flex items-center gap-2 rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50">
                    <span class="material-symbols-outlined !text-xl" :class="submitting && 'animate-spin'" x-text="submitting ? 'progress_activity' : 'filter_alt'"></span>
                    <span x-text="submitting ? '{{ __('جارٍ التطبيق...') }}' : '{{ __('تطبيق') }}'"></span>
                </button>
                @if ($hasFilters)
                    <a href="{{ route('activity-log.index') }}" aria-label="{{ __('مسح الفلاتر') }}" title="{{ __('مسح الفلاتر') }}" class="flex items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">
                        <span class="material-symbols-outlined !text-xl">close</span>
                    </a>
                @endif
            </div>
        </form>

        <section class="reveal-up tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6" style="--d: 140ms">
            @if ($logs->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">{{ $hasFilters ? 'search_off' : 'fact_check' }}</span>
                    <p class="font-body-lg text-on-surface-variant">{{ $hasFilters ? __('لا نتائج مطابقة') : __('لا نشاط مسجّل بعد') }}</p>
                </div>
            @else
                <ul class="stagger divide-y divide-outline-variant/50">
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
                            <span class="whitespace-nowrap text-[11px] text-on-surface-variant">{{ $log->created_at->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ul>

                {{-- The shared footer, not a bespoke prev/next pair: this page
                     had no page numbers, no per-page selector and no total, so
                     reaching an old entry meant clicking "التالي" repeatedly. --}}
                <x-table-footer :paginator="$logs" :perPage="$perPage" />
            @endif
        </section>
    </div>
@endsection
