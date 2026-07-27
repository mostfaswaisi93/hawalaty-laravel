@extends('layouts.app')

@section('title', __('الإعدادات - حوالاتي'))

@section('content')
    {{--
        Two columns from `lg` up: the form on the left, the read-only licence
        summary as a sidebar on the right.

        It used to be one 768px column centred in a ~1660px content area, so
        roughly half the viewport was empty while the page itself ran long. The
        licence block is reference information, never edited, so it belongs
        beside the form rather than below it.

        Spacing follows one scale throughout - 6 between blocks, 5 inside a
        form, 4 inside a row - instead of the mixed 4/5/6/8 it had.
    --}}
    @php
        $statusLabels = ['trial' => __('تجريبي'), 'active' => __('نشط'), 'suspended' => __('موقوف'), 'expired' => __('منتهٍ')];
        $statusStyles = [
            'active' => 'bg-success-container/20 text-success',
            'trial' => 'bg-secondary-container/20 text-secondary',
            'suspended' => 'bg-error-container/40 text-error',
            'expired' => 'bg-surface-container-high text-on-surface-variant',
        ];
    @endphp

    {{-- No `mx-auto max-w-*` here: every other page in the app is full width
         inside the layout's own `max-w-[1680px] p-inline-lg` <main>, so capping
         this one made the content jump inward by ~250px a side when navigating
         to it. The two-column grid below is what keeps the form from stretching,
         not an arbitrary container width. --}}
    <div class="page-enter space-y-6">
        <header>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الإعدادات') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('أدر بياناتك الشخصية وكلمة مرورك.') }}</p>
        </header>

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-5">

            {{-- profile + password --}}
            <section class="reveal-up tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6 lg:col-span-3">
                <div class="mb-6 flex items-center gap-4">
                    <div class="grid h-12 w-12 flex-shrink-0 place-items-center rounded-2xl bg-primary-container text-lg font-bold text-white">{{ mb_substr($member->name, 0, 1) }}</div>
                    <div class="min-w-0">
                        <h2 class="truncate font-headline-md text-headline-md text-on-surface">{{ __('ملفي الشخصي') }}</h2>
                        <p class="truncate font-body-sm text-body-sm text-outline"><span dir="ltr">{{ $member->email }}</span></p>
                    </div>
                </div>

                <form method="POST" action="{{ route('settings.profile.update') }}" class="space-y-5" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    @method('PUT')

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label for="name" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الاسم') }}</label>
                            <input id="name" name="name" value="{{ old('name', $member->name) }}" required
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            @error('name')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="space-y-1.5">
                            <label for="email" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('البريد الإلكتروني') }}</label>
                            <input id="email" type="email" name="email" value="{{ old('email', $member->email) }}" required dir="ltr"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            @error('email')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="space-y-5 border-t border-outline-variant/50 pt-5">
                        <div class="flex items-start gap-2.5 rounded-xl bg-surface-container-low p-3">
                            <span class="material-symbols-outlined !text-lg text-on-surface-variant">info</span>
                            <p class="font-body-sm text-body-sm text-on-surface-variant">{{ __('لتغيير كلمة المرور، املأ الحقلين أدناه - وإلا اتركهما فارغين.') }}</p>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label for="password" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('كلمة مرور جديدة') }}</label>
                                <input id="password" type="password" name="password" dir="ltr" placeholder="••••••" autocomplete="new-password"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                                @error('password')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="space-y-1.5">
                                <label for="password_confirmation" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('تأكيد كلمة المرور') }}</label>
                                <input id="password_confirmation" type="password" name="password_confirmation" dir="ltr" placeholder="••••••" autocomplete="new-password"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-outline-variant/50 pt-5">
                        <button type="submit" :disabled="submitting"
                            class="press flex items-center gap-2 rounded-xl bg-primary px-6 py-3 font-body-sm font-bold text-on-primary shadow-lg shadow-primary/25 transition-all hover:bg-primary-container hover:text-on-primary-container disabled:cursor-not-allowed disabled:opacity-50"
                            title="{{ __('حفظ التغييرات') }}">
                            <span class="material-symbols-outlined !text-xl" :class="submitting && 'animate-spin'"
                                x-text="submitting ? 'progress_activity' : 'save'">save</span>
                            <span x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ التغييرات'))">{{ __('حفظ التغييرات') }}</span>
                        </button>
                    </div>
                </form>
            </section>

            {{-- business / license info --}}
            @if ($license)
                @php
                    $rows = [
                        ['storefront', 'اسم النشاط', $license->customer_name, false],
                        ['qr_code_2', 'رمز الترخيص', $license->license_code, true],
                        ['workspace_premium', 'الباقة', $license->package?->name ?? '-', false],
                        ['event', 'ينتهي في', $license->expired_at?->translatedFormat('j F Y') ?? '-', false],
                        ['account_balance_wallet', 'الرصيد', number_format($license->customer_balance, 2).' ₪', true],
                    ];
                @endphp

                <section class="reveal-up tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6 lg:col-span-2" style="--d:90ms">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <h2 class="font-headline-md text-headline-md text-on-surface">{{ __('معلومات النشاط') }}</h2>
                        {{-- Status is the one figure worth reading at a glance, so it
                             is a chip in the header rather than a sixth grey row. --}}
                        <span class="inline-flex flex-shrink-0 items-center gap-1.5 rounded-full px-3 py-1 font-body-sm text-body-sm font-bold {{ $statusStyles[$license->status->value] ?? $statusStyles['expired'] }}">
                            <span class="material-symbols-outlined !text-base">verified</span>
                            {{ $statusLabels[$license->status->value] ?? $license->status->value }}
                        </span>
                    </div>

                    <dl class="divide-y divide-outline-variant/50">
                        @foreach ($rows as [$icon, $label, $value, $ltr])
                            <div class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <span class="material-symbols-outlined !text-lg">{{ $icon }}</span>
                                </div>
                                <dt class="font-body-sm text-body-sm text-on-surface-variant">{{ __($label) }}</dt>
                                <dd class="ms-auto min-w-0 truncate text-end font-body-sm font-bold text-on-surface">
                                    @if ($ltr)<span dir="ltr">{{ $value }}</span>@else{{ $value }}@endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif
        </div>
    </div>
@endsection
