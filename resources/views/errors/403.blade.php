@extends('layouts.guest')

@section('title', __('غير مصرح - حوالاتي'))

@section('content')
    <section class="mx-auto flex w-full items-center justify-center px-inline-md py-16 lg:col-span-2">
        <div class="w-full max-w-md text-center">
            <div class="mb-6 flex justify-center">
                <div class="reveal-scale flex h-20 w-20 items-center justify-center rounded-3xl bg-secondary-container/20 text-secondary" style="--d:140ms">
                    <span class="material-symbols-outlined text-4xl">lock</span>
                </div>
            </div>
            <h1 class="mb-3 font-display-lg text-2xl text-primary">{{ __('لا تملك صلاحية الوصول') }}</h1>
            <p class="mb-8 font-body-lg text-on-surface-variant">
                {{ __('ليس لديك الصلاحية اللازمة للقيام بهذا الإجراء. تواصل مع مدير حسابك إذا كنت تعتقد أن هذا غير صحيح.') }}
            </p>
            <a
                href="{{ url()->previous() }}"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 font-headline-md text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container">
                <span>{{ __('العودة للصفحة السابقة') }}</span>
            </a>
        </div>
    </section>
@endsection
