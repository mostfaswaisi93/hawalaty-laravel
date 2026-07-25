@extends('layouts.guest')

@section('title', __('تسجيل الدخول - حوالاتي'))

@section('content')
    <section class="flex items-center justify-center bg-surface px-inline-md py-10 lg:px-24">
        <div class="w-full max-w-md">
            <div class="reveal-up mb-8">
                <h1 class="mb-2 font-display-lg text-display-lg text-primary">{{ __('مرحباً بعودتك') }}</h1>
                <p class="font-body-lg text-on-surface-variant">{{ __('سجّل الدخول لإدارة حوالاتك') }}</p>
            </div>

            <x-flash-messages />

            <form method="POST" action="{{ route('login') }}" class="stagger space-y-5">
                @csrf

                <x-form-field
                    name="licenseCode"
                    label="{{ __('رمز الترخيص') }}"
                    icon="qr_code_2"
                    dir="ltr"
                    placeholder="{{ __('مثال: ORAQLPBU') }}" />

                <x-form-field
                    name="email"
                    type="email"
                    label="{{ __('البريد الإلكتروني') }}"
                    icon="mail"
                    dir="ltr"
                    placeholder="you@example.com" />

                <div class="space-y-2">
                    <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" for="password">
                        {{ __('كلمة المرور') }}
                    </label>
                    <div class="relative" x-data="{ show: false }">
                        <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 left-0 pl-3 text-outline">lock</span>
                        <input
                            id="password"
                            :type="show ? 'text' : 'password'"
                            name="password"
                            dir="ltr"
                            autocomplete="current-password"
                            placeholder="{{ __('أدخل كلمة المرور') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest py-3 pe-12 ps-11 text-body-lg outline-none transition-all focus:border-transparent focus:ring-2 focus:ring-primary" />
                        <button
                            type="button"
                            @click="show = !show"
                            :aria-label="show ? '{{ __('إخفاء كلمة المرور') }}' : '{{ __('إظهار كلمة المرور') }}'"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-outline transition-colors hover:text-primary">
                            <span class="material-symbols-outlined" x-text="show ? 'visibility_off' : 'visibility'"></span>
                        </button>
                    </div>
                    @error('password')
                        <p class="text-body-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="checkbox" name="remember" value="1" class="h-5 w-5 rounded accent-primary" />
                        <span class="font-body-sm text-on-surface-variant">{{ __('تذكّرني') }}</span>
                    </label>
                </div>

                <button
                    type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-4 font-headline-md text-headline-md text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-[0.98]" title="{{ __('تسجيل الدخول') }}">
                    <span>{{ __('تسجيل الدخول') }}</span>
                    <span class="material-symbols-outlined rtl:-scale-x-100">login</span>
                </button>
            </form>

            <div class="mt-8 text-center">
                <span class="font-body-sm text-on-surface-variant">{{ __('ليس لديك حساب؟') }}</span>
                <a href="{{ route('signup') }}" class="px-1 font-body-sm font-bold text-secondary hover:underline" title="{{ __('أنشئ حساباً') }}">
                    {{ __('أنشئ حساباً') }}
                </a>
            </div>
        </div>
    </section>

    {{--
        Brand panel. Fixed-dark in both themes (like the sidebar rail), so its text
        uses literal white/white-alpha rather than the surface tokens, which invert.
        Left-aligned and asymmetric on purpose: the previous centred stack read as a
        splash screen rather than as product framing beside the form.
    --}}
    <section class="relative hidden overflow-hidden bg-gradient-to-br from-[#00236f] via-[#152c72] to-[#1e3a8a] p-inline-lg lg:flex lg:flex-col lg:justify-between">
        {{-- Ambient depth: drifting orbs behind a faint grid. motion-safe so
             reduced-motion users get a static field. --}}
        <div class="pointer-events-none absolute -start-24 -top-24 h-[26rem] w-[26rem] rounded-full bg-secondary-container/25 blur-3xl motion-safe:animate-[admin-orb_16s_ease-in-out_infinite]"></div>
        <div class="pointer-events-none absolute -bottom-32 -end-24 h-[30rem] w-[30rem] rounded-full bg-primary-fixed-dim/20 blur-3xl motion-safe:animate-[admin-orb_20s_ease-in-out_infinite_reverse]"></div>
        <div class="pointer-events-none absolute inset-0 opacity-[0.07]"
            style="background-image: linear-gradient(to right, #fff 1px, transparent 1px), linear-gradient(to bottom, #fff 1px, transparent 1px); background-size: 46px 46px;"></div>

        <div class="relative z-10">
            <div class="reveal-up mb-10 flex items-center gap-4">
                <x-logo />
                <div>
                    <span class="block font-display-lg text-2xl font-bold leading-tight text-white">{{ __('حوالاتي') }}</span>
                    <span class="font-body-sm text-white/60">{{ __('تحويلاتي') }}</span>
                </div>
            </div>

            <h2 class="reveal-up max-w-md font-display-lg text-[38px] font-bold leading-[1.15] text-white" style="--d:80ms">
                {{ __('شريكك الموثوق في') }}<br>
                <span class="bg-gradient-to-l from-secondary-fixed-dim to-white bg-clip-text text-transparent rtl:bg-gradient-to-r">
                    {{ __('عالم التحويلات المالية') }}
                </span>
            </h2>

            <p class="reveal-up mt-5 max-w-md font-body-lg leading-relaxed text-white/70" style="--d:140ms">
                {{ __('نوفّر لك أفضل التقنيات العالمية لضمان سرعة وأمان أموالك، وتحكّم كامل في معاملاتك الدولية والمحلية بنقرة واحدة.') }}
            </p>
        </div>

        {{-- Feature cards as glass tiles that lift on hover, rather than a flat
             tick-list - gives the panel a second focal point lower down. --}}
        <ul class="stagger relative z-10 mt-12 space-y-3">
            @foreach ([
                ['icon' => 'bolt', 'title' => __('تتبّع الحوالات في الوقت الفعلي'), 'note' => __('حالة كل معاملة لحظة بلحظة')],
                ['icon' => 'account_balance', 'title' => __('دعم متعدد البنوك والعملات'), 'note' => __('بنوك ومحافظ إلكترونية في مكان واحد')],
                ['icon' => 'insights', 'title' => __('تقارير احترافية ثنائية اللغة'), 'note' => __('تصدير PDF وCSV جاهز للمحاسبة')],
            ] as $feature)
                <li class="flex items-center gap-4 rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur transition-transform duration-300 hover:-translate-y-1">
                    <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-white/15 text-white">
                        <span class="material-symbols-outlined">{{ $feature['icon'] }}</span>
                    </span>
                    <span class="min-w-0">
                        <span class="block font-body-lg font-bold text-white">{{ $feature['title'] }}</span>
                        <span class="block font-body-sm text-[13px] text-white/60">{{ $feature['note'] }}</span>
                    </span>
                </li>
            @endforeach
        </ul>
    </section>
@endsection
