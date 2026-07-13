@extends('layouts.guest')

@section('title', 'دخول المشرف — حوالاتي')

@section('content')
    <section class="mx-auto flex w-full items-center justify-center px-inline-md py-16 lg:col-span-2">
        <div class="w-full max-w-sm">
            <div class="mb-8 text-center">
                <div class="mb-4 flex justify-center">
                    <x-logo />
                </div>
                <h1 class="mb-2 font-display-lg text-2xl text-primary">لوحة تحكم المنصّة</h1>
                <p class="font-body-lg text-on-surface-variant">دخول فريق حوالاتي الإداري</p>
            </div>

            <x-flash-messages />

            <form method="POST" action="{{ route('admin.login') }}" class="space-y-5">
                @csrf

                <x-form-field
                    name="email"
                    type="email"
                    label="البريد الإلكتروني"
                    icon="mail"
                    dir="ltr"
                    placeholder="admin@hawalaty.test" />

                <x-form-field
                    name="password"
                    type="password"
                    label="كلمة المرور"
                    icon="lock"
                    dir="ltr"
                    autocomplete="current-password" />

                <button
                    type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-4 font-headline-md text-headline-md text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-[0.98]">
                    <span>تسجيل الدخول</span>
                    <span class="material-symbols-outlined rtl:-scale-x-100">login</span>
                </button>
            </form>
        </div>
    </section>
@endsection
