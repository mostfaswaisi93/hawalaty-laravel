@extends('layouts.guest')

@section('title', __('إنشاء حساب - حوالاتي'))

@section('content')
    <section class="flex items-center justify-center bg-surface px-inline-md py-10 lg:px-24">
        {{-- Signup provisions a whole tenant - a physical database, its user and
             the full migration run - so this is the longest wait in the app and
             the one where a second click does the most damage. --}}
        <div class="w-full max-w-md" x-data="{ submitting: false }">
            <div class="reveal-up mb-8">
                <h1 class="mb-2 font-display-lg text-display-lg text-primary">{{ __('أنشئ حساب عملك') }}</h1>
                <p class="font-body-lg text-on-surface-variant">{{ __('جهّز مساحة عمل حوالاتي الخاصة بك خلال دقائق') }}</p>
            </div>

            <x-flash-messages />

            <form method="POST" action="{{ route('signup') }}" class="stagger space-y-5" @submit="submitting = true">
                @csrf

                <div class="space-y-4 rounded-xl border border-outline-variant/50 p-4">
                    <p class="font-label-caps text-[11px] uppercase tracking-wider text-primary">{{ __('معلومات النشاط التجاري') }}</p>

                    <x-form-field
                        name="businessName"
                        label="{{ __('اسم النشاط التجاري') }}"
                        icon="storefront"
                        placeholder="{{ __('مثال: بيت التميس') }}" />

                    <x-form-field
                        name="phone"
                        type="tel"
                        label="{{ __('رقم الهاتف') }}"
                        icon="phone_iphone"
                        dir="ltr"
                        placeholder="+970 59 000 0000" />
                </div>

                <div class="space-y-4 rounded-xl border border-outline-variant/50 p-4">
                    <p class="font-label-caps text-[11px] uppercase tracking-wider text-primary">{{ __('حساب المالك') }}</p>

                    <x-form-field
                        name="ownerName"
                        label="{{ __('اسمك') }}"
                        icon="person"
                        placeholder="{{ __('مثال: أحمد علي') }}" />

                    <x-form-field
                        name="ownerEmail"
                        type="email"
                        label="{{ __('البريد الإلكتروني') }}"
                        icon="mail"
                        dir="ltr"
                        placeholder="name@example.com" />

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
                                autocomplete="new-password"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest py-3 pe-12 ps-11 text-body-lg outline-none transition-all focus:border-transparent focus:ring-2 focus:ring-primary" />
                            <button type="button" @click="show = !show" :aria-label="show ? '{{ __('إخفاء كلمة المرور') }}' : '{{ __('إظهار كلمة المرور') }}'" class="absolute inset-y-0 right-0 flex items-center pr-3 text-outline transition-colors hover:text-primary">
                                <span class="material-symbols-outlined" x-text="show ? 'visibility_off' : 'visibility'"></span>
                            </button>
                        </div>
                        <p class="text-xs text-on-surface-variant">{{ __('يجب ألا تقل عن 6 أحرف') }}</p>
                        @error('password')
                            <p class="text-body-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-form-field
                        name="confirmPassword"
                        type="password"
                        label="{{ __('تأكيد كلمة المرور') }}"
                        icon="lock"
                        dir="ltr" />
                </div>

                <label class="flex cursor-pointer items-start gap-2">
                    <input type="checkbox" name="terms" value="1" class="mt-0.5 h-5 w-5 rounded accent-primary" />
                    <span class="font-body-sm text-on-surface-variant">{{ __('أوافق على شروط الخدمة وسياسة الخصوصية') }}</span>
                </label>
                @error('terms')
                    <p class="text-body-sm text-error">{{ $message }}</p>
                @enderror

                <button
                    type="submit"
                    :disabled="submitting"
                    {{-- `!opacity-60` because this form carries `.stagger`, whose
                         reveal-up runs with fill-mode:both - an animation's value
                         outranks a normal declaration, so the plain utility would
                         dim nothing. Same reason as the login button. --}}
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-4 font-headline-md text-headline-md text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-[0.98] disabled:cursor-not-allowed disabled:!opacity-60" title="{{ __('إنشاء الحساب') }}">
                    <span x-text="submitting ? @js(__('جارٍ إنشاء الحساب...')) : @js(__('إنشاء الحساب'))">{{ __('إنشاء الحساب') }}</span>
                    <span class="material-symbols-outlined rtl:-scale-x-100" :class="submitting && 'animate-spin'"
                        x-text="submitting ? 'progress_activity' : 'arrow_forward'">arrow_forward</span>
                </button>
            </form>

            <div class="mt-8 text-center">
                <span class="font-body-sm text-on-surface-variant">{{ __('لديك حساب بالفعل؟') }}</span>
                <a href="{{ route('login') }}" class="px-1 font-body-sm font-bold text-secondary hover:underline" title="{{ __('تسجيل الدخول') }}">
                    {{ __('تسجيل الدخول') }}
                </a>
            </div>
        </div>
    </section>

    <section class="relative hidden items-center justify-center overflow-hidden bg-primary-container p-inline-lg lg:flex">
        <div class="pointer-events-none absolute inset-0 opacity-30">
            <div class="absolute -top-1/4 start-[-10%] h-96 w-96 rounded-full bg-primary blur-3xl"></div>
            <div class="absolute -bottom-1/4 end-[-10%] h-96 w-96 rounded-full bg-secondary-container blur-3xl"></div>
        </div>

        <div class="stagger relative z-10 max-w-lg text-center">
            <div class="mb-8 flex justify-center">
                <div class="flex h-24 w-24 items-center justify-center rounded-3xl border border-white/20 bg-white/10 shadow-xl backdrop-blur-md">
                    <span class="material-symbols-outlined text-5xl text-white" data-weight="fill">storefront</span>
                </div>
            </div>
            <h2 class="mb-4 font-display-lg text-[36px] leading-tight text-white">{{ __('أدر نشاطك التجاري من مساحة عمل واحدة ثنائية اللغة') }}</h2>
            <p class="font-body-lg leading-relaxed text-white/80">
                {{ __('الطلبات، القائمة، البنوك، المصروفات، الفريق والتقارير - كل ذلك في مكان واحد، جاهز خلال دقائق.') }}
            </p>

            <ul class="mt-10 inline-block space-y-3 text-start">
                @foreach ([__('مساحة عمل خاصة ومعزولة بالكامل'), __('ادعُ الموظفين مع صلاحيات مبنية على الأدوار'), __('سجل نشاطات كامل لكل إجراء')] as $feature)
                    <li class="flex items-center gap-3 text-white/90">
                        <span class="material-symbols-outlined text-secondary-container" data-weight="fill">check_circle</span>
                        <span class="font-body-lg">{{ $feature }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endsection
