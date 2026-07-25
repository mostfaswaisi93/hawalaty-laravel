@extends('layouts.app')

@section('title', __('الأجهزة - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6" x-data="{ confirmOpen: false, confirmMessage: '', confirmFormId: null }">
        <header>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('أجهزتي') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('الأجهزة التي سجّلت الدخول منها إلى حسابك — يمكنك إلغاء أيّ منها.') }}</p>
        </header>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($devices->isEmpty())
                <div class="flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">devices</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد أجهزة مسجّلة') }}</p>
                    <p class="font-body-sm text-outline">{{ __('تظهر الأجهزة هنا عند تسجيل الدخول من تطبيق الجوال.') }}</p>
                </div>
            @else
                <ul class="divide-y divide-outline-variant/50">
                    @foreach ($devices as $device)
                        <li class="flex items-center gap-4 py-4">
                            <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                <span class="material-symbols-outlined !text-2xl">{{ in_array($device->platform_icon, ['smartphone', 'computer', 'laptop', 'tablet', 'phone_iphone', 'phone_android'], true) ? $device->platform_icon : 'devices' }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="truncate font-body-lg font-bold text-on-surface">{{ $device->name ?: __('جهاز غير معروف') }}</p>
                                    @if ($device->is_current)
                                        <span class="rounded-full bg-success-container px-2.5 py-0.5 text-[10px] font-bold text-success dark:bg-success-container/15">{{ __('الحالي') }}</span>
                                    @endif
                                </div>
                                <p class="truncate text-[12px] text-outline">
                                    {{ $device->browser ?: '—' }}
                                    @if ($device->last_active_at)
                                        · {{ __('آخر نشاط') }} {{ $device->last_active_at->diffForHumans() }}
                                    @endif
                                </p>
                            </div>
                            <form method="POST" action="{{ route('devices.destroy', $device->id) }}" id="delete-device-{{ $device->id }}" class="inline-flex">
                                @csrf @method('DELETE')
                            </form>
                            <button type="button"
                                @click="confirmOpen = true; confirmMessage = '{{ __('إلغاء هذا الجهاز وتسجيل الخروج منه؟') }}'; confirmFormId = 'delete-device-{{ $device->id }}'"
                                class="flex items-center gap-1.5 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2 font-body-sm font-bold text-on-surface-variant transition-colors hover:border-error/40 hover:bg-error/10 hover:text-error">
                                <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">logout</span>
                                {{ __('إلغاء') }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <x-confirm-modal />
    </div>
@endsection
