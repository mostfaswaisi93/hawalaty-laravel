@extends('layouts.guest')

@section('title', __('دخول المشرف - حوالاتي'))

@section('content')
    {{--
        Fills BOTH columns of the guest layout's lg:grid-cols-2 so the page reads
        as one composition: a fixed-dark brand panel and the form beside it.
        The panel is fixed-dark in both themes (like the sidebar rail), so its
        text uses literal white rather than the surface tokens, which invert.
    --}}
    <section class="relative order-2 hidden overflow-hidden bg-gradient-to-br from-[#00236f] via-[#132a6b] to-[#1e3a8a] lg:flex lg:flex-col lg:justify-between lg:p-12">
        {{-- Ambient depth: two slow-drifting blurred orbs + a faint grid. --}}
        <div class="pointer-events-none absolute -start-24 -top-24 h-96 w-96 rounded-full bg-secondary-container/25 blur-3xl motion-safe:animate-[admin-orb_14s_ease-in-out_infinite]"></div>
        <div class="pointer-events-none absolute -bottom-32 -end-24 h-[28rem] w-[28rem] rounded-full bg-primary-fixed-dim/20 blur-3xl motion-safe:animate-[admin-orb_18s_ease-in-out_infinite_reverse]"></div>
        <div class="pointer-events-none absolute inset-0 opacity-[0.07]"
            style="background-image: linear-gradient(to right, #fff 1px, transparent 1px), linear-gradient(to bottom, #fff 1px, transparent 1px); background-size: 44px 44px;"></div>

        <div class="relative">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 font-body-sm text-body-sm font-bold text-white backdrop-blur">
                <span class="material-symbols-outlined !text-base">shield_person</span>
                {{ __('منطقة إدارية محمية') }}
            </span>

            <h2 class="mt-8 font-display-lg text-4xl font-bold leading-tight text-white xl:text-5xl">
                {{ __('تحكّم كامل') }}<br>
                <span class="bg-gradient-to-l from-secondary-fixed-dim to-white bg-clip-text text-transparent rtl:bg-gradient-to-r">
                    {{ __('في منصّتك') }}
                </span>
            </h2>
            <p class="mt-4 max-w-md font-body-lg leading-relaxed text-white/70">
                {{ __('التراخيص، الباقات، المحاسبة والنسخ الاحتياطي - كل ما تحتاجه لتشغيل المنصّة من مكان واحد.') }}
            </p>
        </div>

        {{-- Feature chips: glass cards that lift on hover. --}}
        <div class="relative grid grid-cols-2 gap-3">
            @foreach ([
                ['icon' => 'key', 'label' => __('إدارة التراخيص')],
                ['icon' => 'query_stats', 'label' => __('محاسبة وتقارير')],
                ['icon' => 'backup', 'label' => __('نسخ احتياطي')],
                ['icon' => 'fact_check', 'label' => __('سجل تدقيق كامل')],
            ] as $feature)
                <div class="flex items-center gap-3 rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur transition-transform duration-300 hover:-translate-y-1">
                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-white/15 text-white">
                        <span class="material-symbols-outlined !text-xl">{{ $feature['icon'] }}</span>
                    </span>
                    <span class="font-body-sm text-body-sm font-bold text-white">{{ $feature['label'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ───────────────────────────── Form column ───────────────────────────── --}}
    <section class="order-1 flex w-full items-center justify-center px-inline-md py-16 md:px-inline-lg">
        <div class="w-full max-w-md" x-data="{ submitting: false }">
            <div class="mb-8">
                <div class="mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-primary text-on-primary shadow-lg shadow-primary/25">
                    <span class="material-symbols-outlined !text-3xl" style="font-variation-settings: 'FILL' 1">shield_person</span>
                </div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('لوحة تحكم المنصّة') }}</h1>
                <p class="mt-2 font-body-lg text-on-surface-variant">{{ __('دخول فريق حوالاتي الإداري') }}</p>
            </div>

            <x-flash-messages />

            <form method="POST" action="{{ route('admin.login') }}" class="space-y-5" @submit="submitting = true">
                @csrf

                <x-form-field
                    name="email"
                    type="email"
                    label="{{ __('البريد الإلكتروني') }}"
                    icon="mail"
                    dir="ltr"
                    placeholder="admin@hawalaty.test" />

                {{-- Hand-rolled rather than <x-form-field>, matching auth/login: the
                     shared component renders a bare input with no visibility toggle.
                     Physical left/right is correct because the input is dir="ltr"
                     regardless of page locale. --}}
                <div class="space-y-2">
                    <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" for="password">
                        {{ __('كلمة المرور') }}
                    </label>
                    <div class="relative" x-data="{ show: false }">
                        <span class="material-symbols-outlined pointer-events-none absolute left-0 top-1/2 -translate-y-1/2 pl-3 text-outline">lock</span>
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

                {{-- AdminLoginRequest already validates `remember` and the controller
                     already passes it to Auth::attempt() - the field was simply never
                     rendered, so the feature was unreachable. --}}
                <label class="flex w-fit cursor-pointer items-center gap-2">
                    <input type="checkbox" name="remember" value="1" class="h-5 w-5 rounded accent-primary" />
                    <span class="font-body-sm text-on-surface-variant">{{ __('تذكّرني') }}</span>
                </label>

                <button
                    type="submit"
                    :disabled="submitting"
                    class="group relative flex w-full items-center justify-center gap-2 overflow-hidden rounded-xl bg-primary py-4 font-headline-md text-headline-md text-on-primary shadow-lg shadow-primary/25 transition-all hover:bg-primary-container hover:text-on-primary-container hover:shadow-xl active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60">
                    {{-- Sheen sweep on hover; purely decorative. --}}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/25 to-transparent transition-transform duration-700 group-hover:translate-x-full"></span>
                    <span x-text="submitting ? @js(__('جارٍ الدخول...')) : @js(__('تسجيل الدخول'))">{{ __('تسجيل الدخول') }}</span>
                    <span class="material-symbols-outlined rtl:-scale-x-100" :class="submitting && 'animate-spin'"
                        x-text="submitting ? 'progress_activity' : 'login'">login</span>
                </button>
            </form>

            <p class="mt-8 flex items-center justify-center gap-1.5 font-body-sm text-body-sm text-outline">
                <span class="material-symbols-outlined !text-base">lock</span>
                {{ __('اتصال مُشفّر ومحمي') }}
            </p>
        </div>
    </section>
@endsection
