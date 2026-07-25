@extends('layouts.guest')

@section('title', __('دخول المشرف — حوالاتي'))

@section('content')
    <section class="mx-auto flex w-full items-center justify-center px-inline-md py-16 lg:col-span-2">
        <div class="w-full max-w-sm">
            <div class="mb-8 text-center">
                <div class="mb-4 flex justify-center">
                    <x-logo />
                </div>
                <h1 class="mb-2 font-display-lg text-2xl text-primary">{{ __('لوحة تحكم المنصّة') }}</h1>
                <p class="font-body-lg text-on-surface-variant">{{ __('دخول فريق حوالاتي الإداري') }}</p>
            </div>

            <x-flash-messages />

            <form method="POST" action="{{ route('admin.login') }}" class="space-y-5">
                @csrf

                <x-form-field
                    name="email"
                    type="email"
                    label="{{ __('البريد الإلكتروني') }}"
                    icon="mail"
                    dir="ltr"
                    placeholder="admin@hawalaty.test" />

                {{-- Hand-rolled rather than <x-form-field>, matching auth/login.blade.php: the shared
                     component renders a bare input with no visibility toggle. Physical left/right here
                     is correct because the input is dir="ltr" regardless of page locale. --}}
                <div class="space-y-2">
                    <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" for="password">
                        {{ __('كلمة المرور') }}
                    </label>
                    <div class="relative" x-data="{ show: false }">
                        <span class="material-symbols-outlined pointer-events-none absolute top-1/2 left-0 -translate-y-1/2 pl-3 text-outline">lock</span>
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

                {{-- AdminLoginRequest already validates `remember` and AdminAuthController already passes
                     it to Auth::attempt() — the field was simply never rendered, so it was dead. --}}
                <label class="flex cursor-pointer items-center gap-2">
                    <input type="checkbox" name="remember" value="1" class="h-5 w-5 rounded accent-primary" />
                    <span class="font-body-sm text-on-surface-variant">{{ __('تذكّرني') }}</span>
                </label>

                <button
                    type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-4 font-headline-md text-headline-md text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-[0.98]">
                    <span>{{ __('تسجيل الدخول') }}</span>
                    <span class="material-symbols-outlined rtl:-scale-x-100">login</span>
                </button>
            </form>
        </div>
    </section>
@endsection
