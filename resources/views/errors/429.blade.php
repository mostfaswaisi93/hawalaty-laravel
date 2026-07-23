@extends('layouts.guest')

@section('title', __('محاولات كثيرة — حوالاتي'))

@section('content')
    <section class="mx-auto flex w-full items-center justify-center px-inline-md py-16 lg:col-span-2">
        <div class="w-full max-w-md text-center">
            <div class="mb-6 flex justify-center">
                <div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-secondary-container/20 text-secondary">
                    <span class="material-symbols-outlined text-4xl">hourglass_top</span>
                </div>
            </div>
            <h1 class="mb-3 font-display-lg text-2xl text-primary">{{ __('محاولات كثيرة جداً') }}</h1>
            <p class="mb-8 font-body-lg text-on-surface-variant">
                {{ __('لقد تجاوزت الحد المسموح به من المحاولات. يرجى الانتظار قليلاً ثم إعادة المحاولة.') }}
            </p>
            <a
                href="{{ url()->previous() }}"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 font-headline-md text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container">
                <span>{{ __('إعادة المحاولة') }}</span>
            </a>
        </div>
    </section>
@endsection
