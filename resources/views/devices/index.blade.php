@extends('layouts.app')

@section('title', __('الأجهزة - حوالاتي'))

@section('content')
    @php
        $pct = $usage['unlimited'] || $usage['limit'] === 0
            ? 0
            : min(100, (int) round($usage['used'] / $usage['limit'] * 100));
        $full = ! $usage['unlimited'] && $usage['remaining'] === 0;
    @endphp

    <div class="page-enter space-y-6" x-data="{ confirmOpen: false, confirmMessage: '', confirmFormId: null, confirmTitle: '', confirmLabel: '' }">
        <header>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ $manages ? __('الأجهزة') : __('أجهزتي') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">
                {{ $manages
                    ? __('كل الأجهزة المرتبطة بالحساب - يمكنك إلغاء أيّ منها.')
                    : __('الأجهزة التي سجّلت الدخول منها إلى حسابك - يمكنك إلغاء أيّ منها.') }}
            </p>
        </header>

        <section class="reveal-up tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl {{ $full ? 'bg-error/10 text-error' : 'bg-primary/10 text-primary' }}">
                        <span class="material-symbols-outlined">{{ $full ? 'gpp_maybe' : 'devices' }}</span>
                    </span>
                    <div>
                        <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الأجهزة المستخدمة') }}</p>
                        <p class="mt-0.5 font-body-lg font-bold text-on-surface">
                            @if ($usage['unlimited'])
                                <span dir="ltr">{{ $usage['used'] }}</span> - {{ __('غير محدود') }}
                            @else
                                <span dir="ltr">{{ $usage['used'] }} / {{ $usage['limit'] }}</span>
                            @endif
                        </p>
                    </div>
                </div>

                @unless ($usage['unlimited'])
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 font-body-sm text-body-sm font-bold {{ $full ? 'bg-error-container/40 text-error' : 'bg-success-container/20 text-success' }}">
                        <span class="material-symbols-outlined !text-base">{{ $full ? 'block' : 'check_circle' }}</span>
                        {{ $full ? __('بلغت الحد الأقصى') : __(':count متبقٍ', ['count' => $usage['remaining']]) }}
                    </span>
                @endunless
            </div>

            @unless ($usage['unlimited'])
                <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-surface-container-high"
                    role="progressbar" aria-valuenow="{{ $usage['used'] }}" aria-valuemin="0" aria-valuemax="{{ $usage['limit'] }}"
                    aria-label="{{ __('الأجهزة المستخدمة') }}">
                    <div class="grow-meter h-full rounded-full {{ $full ? 'bg-error' : 'bg-primary' }}" style="width: {{ $pct }}%"></div>
                </div>
                <p class="mt-3 font-body-sm text-body-sm text-outline">
                    {{ __('يُحدَّد هذا العدد من باقة اشتراكك. لإضافة جهاز جديد بعد بلوغ الحد، ألغِ جهازاً من القائمة أدناه.') }}
                </p>
            @endunless
        </section>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($devices->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">devices</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد أجهزة مسجّلة') }}</p>
                    <p class="font-body-sm text-outline">{{ __('تظهر الأجهزة هنا عند تسجيل الدخول من تطبيق الجوال.') }}</p>
                </div>
            @else
                <ul class="stagger divide-y divide-outline-variant/50">
                    @foreach ($devices as $device)
                        <li class="flex items-center gap-4 py-4">
                            <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                <span class="material-symbols-outlined !text-2xl">{{ in_array($device->platform_icon, ['smartphone', 'computer', 'laptop', 'tablet', 'phone_iphone', 'phone_android'], true) ? $device->platform_icon : 'devices' }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate font-body-lg font-bold text-on-surface">{{ $device->name ?: __('جهاز غير معروف') }}</p>
                                    @if ($device->is_current)
                                        <span class="rounded-full bg-success-container px-2.5 py-0.5 text-[10px] font-bold text-success dark:bg-success-container/15">{{ __('الحالي') }}</span>
                                    @endif
                                    @if ($manages && $device->teamMember)
                                        <span class="rounded-full bg-surface-container-high px-2.5 py-0.5 text-[10px] font-bold text-on-surface-variant">{{ $device->teamMember->name }}</span>
                                    @endif
                                </div>
                                <p class="truncate text-[12px] text-outline">
                                    {{ $device->browser ?: '-' }}
                                    @if ($device->last_active_at)
                                        · {{ __('آخر نشاط') }} {{ $device->last_active_at->diffForHumans() }}
                                    @endif
                                </p>
                            </div>
                            <form method="POST" action="{{ route('devices.destroy', $device->id) }}" id="delete-device-{{ $device->id }}" class="inline-flex">
                                @csrf @method('DELETE')
                            </form>
                            <button type="button"
                                @click="confirmOpen = true;
                                        confirmTitle = @js(__('إلغاء الجهاز'));
                                        confirmMessage = @js(__('إلغاء هذا الجهاز وتسجيل الخروج منه؟'));
                                        confirmLabel = @js(__('إلغاء الجهاز'));
                                        confirmFormId = 'delete-device-{{ $device->id }}'"
                                class="flex flex-shrink-0 items-center gap-1.5 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2 font-body-sm font-bold text-on-surface-variant transition-colors hover:border-error/40 hover:bg-error/10 hover:text-error"
                                title="{{ __('إلغاء الجهاز') }}">
                                <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">logout</span>
                                <span class="hidden sm:inline">{{ __('إلغاء') }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <x-confirm-modal />
    </div>
@endsection
