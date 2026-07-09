@extends('layouts.guest')

@section('title', 'إنشاء حساب — حوالاتي')

@section('content')
    <section class="flex w-full items-center justify-center bg-surface px-inline-md py-10 lg:w-1/2 lg:px-24">
        <div class="w-full max-w-md">
            <div class="mb-8">
                <h1 class="mb-2 font-display-lg text-display-lg text-primary">أنشئ حساب عملك</h1>
                <p class="font-body-lg text-on-surface-variant">جهّز مساحة عمل حوالاتي الخاصة بك خلال دقائق</p>
            </div>

            <x-flash-messages />

            <form method="POST" action="{{ route('signup') }}" class="space-y-5">
                @csrf

                <div class="space-y-4 rounded-xl border border-outline-variant/50 p-4">
                    <p class="font-label-caps text-[11px] uppercase tracking-wider text-primary">معلومات النشاط التجاري</p>

                    <x-form-field
                        name="businessName"
                        label="اسم النشاط التجاري"
                        icon="storefront"
                        placeholder="مثال: بيت التميس" />

                    <x-form-field
                        name="phone"
                        type="tel"
                        label="رقم الهاتف"
                        icon="phone_iphone"
                        dir="ltr"
                        placeholder="+970 59 000 0000" />
                </div>

                <div class="space-y-4 rounded-xl border border-outline-variant/50 p-4">
                    <p class="font-label-caps text-[11px] uppercase tracking-wider text-primary">حساب المالك</p>

                    <x-form-field
                        name="ownerName"
                        label="اسمك"
                        icon="person"
                        placeholder="مثال: أحمد علي" />

                    <x-form-field
                        name="ownerEmail"
                        type="email"
                        label="البريد الإلكتروني"
                        icon="mail"
                        dir="ltr"
                        placeholder="name@example.com" />

                    <div class="space-y-2">
                        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" for="password">
                            كلمة المرور
                        </label>
                        <div class="relative" x-data="{ show: false }">
                            <span class="material-symbols-outlined pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-outline">lock</span>
                            <input
                                id="password"
                                :type="show ? 'text' : 'password'"
                                name="password"
                                dir="ltr"
                                autocomplete="new-password"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest py-3 pe-12 ps-11 text-body-lg outline-none transition-all focus:border-transparent focus:ring-2 focus:ring-primary" />
                            <button type="button" @click="show = !show" class="absolute inset-y-0 end-0 flex items-center pe-3 text-outline transition-colors hover:text-primary">
                                <span class="material-symbols-outlined" x-text="show ? 'visibility_off' : 'visibility'"></span>
                            </button>
                        </div>
                        <p class="text-xs text-on-surface-variant">يجب ألا تقل عن 6 أحرف</p>
                        @error('password')
                            <p class="text-body-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-form-field
                        name="confirmPassword"
                        type="password"
                        label="تأكيد كلمة المرور"
                        icon="lock"
                        dir="ltr" />
                </div>

                <label class="flex cursor-pointer items-start gap-2">
                    <input type="checkbox" name="terms" value="1" class="mt-0.5 h-5 w-5 rounded accent-primary" />
                    <span class="font-body-sm text-on-surface-variant">أوافق على شروط الخدمة وسياسة الخصوصية</span>
                </label>
                @error('terms')
                    <p class="text-body-sm text-error">{{ $message }}</p>
                @enderror

                <button
                    type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-4 font-headline-md text-headline-md text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-[0.98]">
                    <span>إنشاء الحساب</span>
                    <span class="material-symbols-outlined rtl:-scale-x-100">arrow_forward</span>
                </button>
            </form>

            <div class="mt-8 text-center">
                <span class="font-body-sm text-on-surface-variant">لديك حساب بالفعل؟</span>
                <a href="{{ route('login') }}" class="px-1 font-body-sm font-bold text-secondary hover:underline">
                    تسجيل الدخول
                </a>
            </div>
        </div>
    </section>

    <section class="relative hidden items-center justify-center overflow-hidden bg-primary-container p-inline-lg lg:flex lg:w-1/2">
        <div class="pointer-events-none absolute inset-0 opacity-30">
            <div class="absolute -top-1/4 start-[-10%] h-96 w-96 rounded-full bg-primary blur-3xl"></div>
            <div class="absolute -bottom-1/4 end-[-10%] h-96 w-96 rounded-full bg-secondary-container blur-3xl"></div>
        </div>

        <div class="relative z-10 max-w-lg text-center">
            <div class="mb-8 flex justify-center">
                <div class="flex h-24 w-24 items-center justify-center rounded-3xl border border-white/20 bg-white/10 shadow-xl backdrop-blur-md">
                    <span class="material-symbols-outlined text-5xl text-white" data-weight="fill">storefront</span>
                </div>
            </div>
            <h2 class="mb-4 font-display-lg text-[36px] leading-tight text-white">أدر نشاطك التجاري من مساحة عمل واحدة ثنائية اللغة</h2>
            <p class="font-body-lg leading-relaxed text-white/80">
                الطلبات، القائمة، البنوك، المصروفات، الفريق والتقارير — كل ذلك في مكان واحد، جاهز خلال دقائق.
            </p>

            <ul class="mt-10 inline-block space-y-3 text-start">
                @foreach (['مساحة عمل خاصة ومعزولة بالكامل', 'ادعُ الموظفين مع صلاحيات مبنية على الأدوار', 'سجل نشاطات كامل لكل إجراء'] as $feature)
                    <li class="flex items-center gap-3 text-white/90">
                        <span class="material-symbols-outlined text-secondary-container" data-weight="fill">check_circle</span>
                        <span class="font-body-lg">{{ $feature }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endsection
