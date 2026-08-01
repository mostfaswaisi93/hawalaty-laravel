@extends('layouts.admin')

@section('title', __('السجلات - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6">
        <header>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('سجل النشاطات') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('سجل تدقيق لجميع إجراءات المشرفين (للقراءة فقط).') }}</p>
        </header>

        <form method="GET" action="{{ route('admin.logs.index') }}" class="reveal-up flex gap-2" style="--d: 80ms"
            x-data="{ filtering: false }" @submit="filtering = true">
            <x-keep-per-page />
            <select name="adminId" aria-label="{{ __('المشرف') }}" class="max-w-xs rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                <option value="">{{ __('كل المشرفين') }}</option>
                @foreach ($admins as $admin)
                    <option value="{{ $admin->id }}" @selected(($adminId ?? '') === $admin->id)>{{ $admin->name }}</option>
                @endforeach
            </select>
            <button type="submit" :disabled="filtering" class="flex items-center justify-center rounded-xl bg-surface-container px-4 text-on-surface transition-colors hover:bg-surface-container-high disabled:cursor-not-allowed disabled:opacity-60" aria-label="{{ __('تصفية') }}" title="{{ __('تصفية') }}"
                @mouseenter="showTipBelow($el, @js(__('تصفية')))" @mouseleave="hideTip()" @focus="showTipBelow($el, @js(__('تصفية')))" @blur="hideTip()">
                <span class="material-symbols-outlined" :class="filtering && 'animate-spin'"
                    x-text="filtering ? 'progress_activity' : 'filter_alt'">filter_alt</span>
            </button>
        </form>

        <section class="tonal-elevation reveal-up rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6" style="--d: 160ms">
            @if ($logs->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center" style="--d: 300ms">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">fact_check</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد سجلات') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[__('المشرف'), __('الإجراء'), __('العنصر'), __('التاريخ')]">
                    <x-slot:table>
                        @foreach ($logs as $log)
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm font-bold">{{ $log->admin?->name ?? '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3"><span class="rounded-full bg-surface-container px-2.5 py-1 text-[11px] font-bold text-on-surface-variant">{{ __($log->action) }}</span></td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $log->entity_type }} · {{ $log->entity_label }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $log->created_at?->translatedFormat('j F Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </x-slot:table>
                    <x-slot:cards>
                        @foreach ($logs as $log)
                            <div class="tonal-elevation rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-body-sm font-bold">{{ $log->admin?->name ?? '-' }}</span>
                                    <span class="rounded-full bg-surface-container px-2.5 py-1 text-[11px] font-bold text-on-surface-variant">{{ __($log->action) }}</span>
                                </div>
                                <p class="mt-1 text-[11px] text-on-surface-variant">{{ $log->entity_type }} · {{ $log->entity_label }}</p>
                                <p class="mt-1 text-[11px] text-on-surface-variant">{{ $log->created_at?->translatedFormat('j M Y H:i') }}</p>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$logs" :perPage="$perPage" />
            @endif
        </section>
    </div>
@endsection
