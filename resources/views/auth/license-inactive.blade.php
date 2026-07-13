@extends('layouts.guest')

@section('title', __('الترخيص غير نشط — حوالاتي'))

@section('content')
    <section class="mx-auto flex w-full items-center justify-center px-inline-md py-16 lg:col-span-2">
        <div class="w-full max-w-md text-center">
            <div class="mb-6 flex justify-center">
                <div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-error-container text-on-error-container">
                    <span class="material-symbols-outlined text-4xl">block</span>
                </div>
            </div>
            <h1 class="mb-3 font-display-lg text-2xl text-primary">{{ __('هذا الترخيص لم يعد نشطاً') }}</h1>
            <p class="mb-8 font-body-lg text-on-surface-variant">
                {{ __('تم تعليق أو انتهاء صلاحية ترخيص حسابك منذ آخر تسجيل دخول. يرجى التواصل مع الدعم لتجديد الترخيص، أو تسجيل الدخول برمز ترخيص آخر.') }}
            </p>
            <a
                href="{{ route('login') }}"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 font-headline-md text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container">
                <span>{{ __('العودة لتسجيل الدخول') }}</span>
            </a>
        </div>
    </section>
@endsection
