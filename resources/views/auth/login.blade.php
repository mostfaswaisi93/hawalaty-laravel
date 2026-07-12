@extends('layouts.guest')

@section('title', 'تسجيل الدخول — حوالاتي')

@section('content')
    <section class="flex items-center justify-center bg-surface px-inline-md py-10 lg:px-24">
        <div class="w-full max-w-md">
            <div class="mb-8">
                <h1 class="mb-2 font-display-lg text-display-lg text-primary">مرحباً بعودتك</h1>
                <p class="font-body-lg text-on-surface-variant">سجّل الدخول لإدارة حوالاتك</p>
            </div>

            <x-flash-messages />

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                <x-form-field
                    name="licenseCode"
                    label="رمز الترخيص"
                    icon="qr_code_2"
                    dir="ltr"
                    placeholder="مثال: ORAQLPBU" />

                <x-form-field
                    name="email"
                    type="email"
                    label="البريد الإلكتروني"
                    icon="mail"
                    dir="ltr"
                    placeholder="you@example.com" />

                <div class="space-y-2">
                    <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" for="password">
                        كلمة المرور
                    </label>
                    <div class="relative" x-data="{ show: false }">
                        <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 left-0 pl-3 text-outline">lock</span>
                        <input
                            id="password"
                            :type="show ? 'text' : 'password'"
                            name="password"
                            dir="ltr"
                            autocomplete="current-password"
                            placeholder="أدخل كلمة المرور"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest py-3 pe-12 ps-11 text-body-lg outline-none transition-all focus:border-transparent focus:ring-2 focus:ring-primary" />
                        <button
                            type="button"
                            @click="show = !show"
                            :aria-label="show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
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
                        <span class="font-body-sm text-on-surface-variant">تذكّرني</span>
                    </label>
                </div>

                <button
                    type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-4 font-headline-md text-headline-md text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-[0.98]">
                    <span>تسجيل الدخول</span>
                    <span class="material-symbols-outlined rtl:-scale-x-100">login</span>
                </button>
            </form>

            <div class="mt-8 text-center">
                <span class="font-body-sm text-on-surface-variant">ليس لديك حساب؟</span>
                <a href="{{ route('signup') }}" class="px-1 font-body-sm font-bold text-secondary hover:underline">
                    أنشئ حساباً
                </a>
            </div>
        </div>
    </section>

    <section class="relative hidden items-center justify-center overflow-hidden bg-primary-container p-inline-lg lg:flex">
        <div class="pointer-events-none absolute inset-0 opacity-30">
            <div class="absolute -top-1/4 start-[-10%] h-96 w-96 rounded-full bg-primary blur-3xl"></div>
            <div class="absolute -bottom-1/4 end-[-10%] h-96 w-96 rounded-full bg-secondary-container blur-3xl"></div>
        </div>

        <div class="relative z-10 max-w-lg text-center">
            <div class="mb-8 flex justify-center">
                <x-logo size="lg" />
            </div>
            <h2 class="mb-4 font-display-lg text-[36px] leading-tight text-white">شريكك الموثوق في عالم التحويلات المالية</h2>
            <p class="font-body-lg leading-relaxed text-white/80">
                نوفّر لك أفضل التقنيات العالمية لضمان سرعة وأمان أموالك، وتحكّم كامل في معاملاتك الدولية والمحلية بنقرة واحدة.
            </p>

            <ul class="mt-10 inline-block space-y-3 text-start">
                @foreach (['تتبّع الحوالات في الوقت الفعلي', 'دعم متعدد البنوك والعملات', 'تقارير احترافية ثنائية اللغة'] as $feature)
                    <li class="flex items-center gap-3 text-white/90">
                        <span class="material-symbols-outlined text-secondary-container" data-weight="fill">check_circle</span>
                        <span class="font-body-lg">{{ $feature }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endsection
