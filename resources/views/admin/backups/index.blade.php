@extends('layouts.admin')

@section('title', __('النسخ الاحتياطي - حوالاتي'))

@php
    $backupStatus = [
        'completed' => ['label' => __('مكتملة'), 'class' => 'bg-primary/10 text-primary'],
        'in_progress' => ['label' => __('جارية'), 'class' => 'bg-secondary/10 text-secondary'],
        'failed' => ['label' => __('فاشلة'), 'class' => 'bg-error/10 text-error'],
    ];
@endphp

@section('content')
    <div class="page-enter space-y-6"
        x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            submitting: false,
            confirmOpen: false, confirmMessage: '', confirmFormId: null,
        }">

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('النسخ الاحتياطي') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('نسخ احتياطية لقاعدة المنصّة أو أي قاعدة مستأجر.') }}</p>
            </div>
            <button type="button" @click="open = true; submitting = false"
                class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95" title="{{ __('نسخة احتياطية جديدة') }}">
                <span class="material-symbols-outlined !text-xl">backup</span>{{ __('نسخة احتياطية جديدة') }}
            </button>
        </header>

        <section class="tonal-elevation reveal-up rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6" style="--d: 80ms">
            @if ($backups->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center" style="--d: 240ms">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">backup</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد نسخ احتياطية بعد') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[__('الهدف'), __('المصدر'), __('الحجم'), __('الحالة'), __('التاريخ'), __('إجراءات')]">
                    <x-slot:table>
                        @foreach ($backups as $backup)
                            @php $meta = $backupStatus[$backup->status->value] ?? ['label' => $backup->status->value, 'class' => 'bg-outline/10 text-outline']; @endphp
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm font-bold">{{ $backup->license?->customer_name ?? __('قاعدة المنصّة') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $backup->trigger->value === 'manual' ? __('يدوي') : __('مجدول') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant" dir="ltr">{{ $backup->size_mb ? $backup->size_mb.' MB' : '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3"><span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $meta['class'] }}">{{ $meta['label'] }}</span></td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $backup->created_at?->translatedFormat('j F Y H:i') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('admin.backups.download', $backup->id) }}" class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تنزيل') }}"><span class="material-symbols-outlined !text-xl">download</span></a>
                                        <form method="POST" action="{{ route('admin.backups.destroy', $backup->id) }}" id="del-backup-{{ $backup->id }}">@csrf @method('DELETE')</form>
                                        <button type="button" aria-label="{{ __('حذف') }}" title="{{ __('حذف') }}" @click="confirmOpen = true; confirmMessage = '{{ __('حذف هذه النسخة الاحتياطية؟') }}'; confirmFormId = 'del-backup-{{ $backup->id }}'" class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant hover:bg-error/10 hover:text-error"><span class="material-symbols-outlined !text-xl">delete</span></button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>
                    <x-slot:cards>
                        @foreach ($backups as $backup)
                            @php $meta = $backupStatus[$backup->status->value] ?? ['label' => $backup->status->value, 'class' => 'bg-outline/10 text-outline']; @endphp
                            <div class="tonal-elevation rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-center justify-between">
                                    <span class="font-body-sm font-bold">{{ $backup->license?->customer_name ?? __('قاعدة المنصّة') }}</span>
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $meta['class'] }}">{{ $meta['label'] }}</span>
                                </div>
                                <p class="mt-1 text-[11px] text-on-surface-variant">{{ $backup->created_at?->translatedFormat('j M Y H:i') }} · {{ $backup->size_mb ? $backup->size_mb.' MB' : '-' }}</p>
                                <div class="mt-3 flex items-center justify-end gap-1 border-t border-outline-variant/50 pt-3">
                                    <a href="{{ route('admin.backups.download', $backup->id) }}" class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تنزيل') }}"><span class="material-symbols-outlined !text-lg">download</span></a>
                                    <form method="POST" action="{{ route('admin.backups.destroy', $backup->id) }}" id="del-backup-card-{{ $backup->id }}">@csrf @method('DELETE')</form>
                                    <button type="button" aria-label="{{ __('حذف') }}" title="{{ __('حذف') }}" @click="confirmOpen = true; confirmMessage = '{{ __('حذف هذه النسخة الاحتياطية؟') }}'; confirmFormId = 'del-backup-card-{{ $backup->id }}'" class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant hover:bg-error/10 hover:text-error"><span class="material-symbols-outlined !text-lg">delete</span></button>
                                </div>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$backups" :perPage="$perPage" />
            @endif
        </section>

        <div x-show="open" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
            <div class="fixed inset-0 bg-black/40" x-show="open" x-transition.opacity></div>
            <div x-show="open" @keydown.escape.window="open = false" role="dialog" aria-modal="true"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto overflow-x-hidden rounded-3xl bg-surface-container-lowest shadow-2xl">
                <h3 class="border-b border-outline-variant/60 px-6 py-4 font-headline-md text-headline-md">{{ __('إنشاء نسخة احتياطية') }}</h3>
                <form method="POST" action="{{ route('admin.backups.store') }}" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf
                    <div class="space-y-1.5">
                        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الهدف') }}</label>
                        <select name="licenseId" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            <option value="">{{ __('قاعدة بيانات المنصّة') }}</option>
                            @foreach ($licenses as $license)
                                <option value="{{ $license->id }}" @selected(old('licenseId') === $license->id)>{{ $license->customer_name }} ({{ $license->license_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="open = false" class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container" title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting" class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:opacity-50" x-text="submitting ? '{{ __('جارٍ الإنشاء...') }}' : '{{ __('إنشاء') }}'"></button>
                    </div>
                </form>
            </div>
        </div>

        <x-confirm-modal />
    </div>
@endsection
