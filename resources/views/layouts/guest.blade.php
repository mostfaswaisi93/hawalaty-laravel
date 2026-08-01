@php $otherLocale = app()->getLocale() === 'ar' ? 'en' : 'ar'; @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="lang-{{ app()->getLocale() }}"
    x-data="{
        dark: localStorage.getItem('theme') === 'dark',
        tipText: '', tipTop: 0, tipLeft: 0,
        // Header icons: sits below the control, horizontally centred. Uses the
        // same styled bubble as the app and admin shells instead of the native
        // `title` attribute, which is slow to appear and cannot be themed.
        //
        // Only the `below` placement exists here - this shell has no sidebar
        // rail, so there is no `collapsed` state for a `side` tooltip to clear.
        showTipBelow(el, text) {
            // Mouse affordance only - on touch, mouseenter fires on tap and the
            // bubble would linger with nothing to dismiss it.
            if (!window.matchMedia('(pointer: fine)').matches) return;
            const r = el.getBoundingClientRect();
            this.tipTop = r.bottom + 10;
            this.tipLeft = r.left + r.width / 2;
            this.tipText = text;
        },
        hideTip() { this.tipText = ''; },
    }"
    x-init="$watch('dark', v => { document.documentElement.classList.toggle('dark', v); localStorage.setItem('theme', v ? 'dark' : 'light') }); document.documentElement.classList.toggle('dark', dark)"
    :class="{ dark: dark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{--
        A child view sets $metaDescription / $metaRobots / $seoSchemas at its top
        level - `@extends` hands the layout `get_defined_vars()`, so a plain
        variable reaches here without a section.

        The default is `noindex`, and the public content pages opt back IN. The
        pages that inherit the default are the auth screens, which have no
        business in a search result: an indexed login form only competes with
        the landing page for the brand query.
    --}}
    <x-seo
        :title="trim($__env->yieldContent('title', __('حوالاتي')))"
        :description="$metaDescription ?? __('منصّة عربية متكاملة لإدارة الطلبات والحسابات والتقارير لأي مشروع.')"
        :robots="$metaRobots ?? 'noindex, follow'"
        type="article"
        :schemas="$seoSchemas ?? []" />

    {{-- No favicon was declared anywhere, so browsers fell back to probing /favicon.ico
         and showed the generic page glyph. The SVG is the same wallet mark as <x-logo>. --}}
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    {{-- PNG, not SVG: iOS ignores SVG for the home screen. --}}
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    {{-- The manifest belongs here too, not only on the signed-in shell: the
         login page is the first thing anyone opens, and a browser only offers
         to install from a page that declares one.

         Excluded on /admin/*, which shares this shell for its own login. The
         manifest's start_url is the TENANT dashboard, so offering an install
         there would put the wrong app on a platform operator's home screen. --}}
    @unless (request()->is('admin/*'))
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="mobile-web-app-capable" content="yes">
    @endunless
    <meta name="theme-color" content="#00236f">
    <x-fonts />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface font-sans text-on-surface antialiased">
    <x-skip-link />
    <div class="page-enter flex min-h-screen flex-col bg-surface text-on-surface">
        <header class="fixed inset-x-0 top-0 z-50 flex h-16 items-center justify-between border-b border-outline-variant bg-surface px-inline-md shadow-sm md:px-inline-lg">
            <a href="{{ route('login') }}" class="flex items-center gap-3" title="{{ __('حوالاتي') }}">
                <x-logo />
                <span class="font-display-lg text-2xl font-bold leading-none text-primary">{{ __('حوالاتي') }}</span>
            </a>
            <div class="flex items-center gap-1">
                {{-- The locale code alone carries the meaning; the translate glyph
                     beside it was pure duplication. --}}
                <a href="{{ route('locale.switch', $otherLocale) }}"
                    @mouseenter="showTipBelow($el, @js(__('تغيير اللغة')))" @mouseleave="hideTip()"
                    @focus="showTipBelow($el, @js(__('تغيير اللغة')))" @blur="hideTip()"
                    class="flex h-10 min-w-10 items-center justify-center rounded-full px-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                    aria-label="{{ __('تغيير اللغة') }}" title="{{ __('تغيير اللغة') }}">
                    {{ $otherLocale === 'en' ? 'EN' : 'ع' }}
                </a>
                {{-- No `:title` here: the styled bubble already names the control,
                     and the native tooltip would fade in on top of it. The label
                     stays available to assistive tech via `:aria-label`. --}}
                <button
                    type="button"
                    @click="dark = !dark"
                    @mouseenter="showTipBelow($el, dark ? @js(__('الوضع الفاتح')) : @js(__('الوضع الداكن')))" @mouseleave="hideTip()"
                    @focus="showTipBelow($el, dark ? @js(__('الوضع الفاتح')) : @js(__('الوضع الداكن')))" @blur="hideTip()"
                    class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                    :aria-label="dark ? '{{ __('الوضع الفاتح') }}' : '{{ __('الوضع الداكن') }}'">
                    <span class="material-symbols-outlined" x-text="dark ? 'light_mode' : 'dark_mode'"></span>
                </button>
            </div>
        </header>

        {{-- Fewer controls ahead of it than the app shell, but /help renders
             here for a guest and runs long. See layouts/app.blade.php for why
             the target is focusable. --}}
        <main id="main-content" tabindex="-1" class="w-full flex-grow pt-16 focus:outline-none lg:grid lg:grid-cols-2">
            @yield('content')
        </main>

        <footer class="z-20 flex flex-col items-center justify-between gap-3 border-t border-outline-variant bg-surface-container-lowest px-inline-md py-stack-md md:flex-row md:px-inline-lg">
            <div class="flex flex-col items-center gap-2 md:flex-row md:gap-4">
                <div class="flex items-center gap-2">
                    <x-logo />
                    <span class="font-headline-md text-headline-md text-primary">{{ __('حوالاتي') }}</span>
                </div>
                <span class="font-body-sm text-on-surface-variant">© {{ date('Y') }} {{ __('حوالاتي') }} · {{ __('جميع الحقوق محفوظة') }}</span>
            </div>
            <div class="flex flex-col items-center gap-3 md:flex-row md:gap-5">
                <nav class="flex flex-wrap justify-center gap-4">
                    <a href="/privacy" class="cursor-pointer font-body-sm text-on-surface-variant transition-colors hover:text-secondary" title="{{ __('سياسة الخصوصية') }}">{{ __('سياسة الخصوصية') }}</a>
                    <a href="/terms" class="cursor-pointer font-body-sm text-on-surface-variant transition-colors hover:text-secondary" title="{{ __('شروط الخدمة') }}">{{ __('شروط الخدمة') }}</a>
                    <a href="/help" class="cursor-pointer font-body-sm text-on-surface-variant transition-colors hover:text-secondary" title="{{ __('مركز المساعدة') }}">{{ __('مركز المساعدة') }}</a>
                </nav>
                <a href="https://github.com/mostfaswaisi93" target="_blank" rel="noopener noreferrer"
                    class="flex items-center gap-1.5 rounded-lg px-2 py-1 font-body-sm text-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                    aria-label="{{ __('حساب GitHub للمطوّر') }}" title="{{ __('حساب GitHub للمطوّر') }}">
                    <span class="flex items-center gap-1.5 font-normal text-outline">
                        {{ __('صُنع بحب') }}
                        <span class="material-symbols-outlined !text-base text-error" style="font-variation-settings: 'FILL' 1" aria-hidden="true">favorite</span>
                    </span>
                    <svg viewBox="0 0 16 16" class="h-4 w-4 flex-shrink-0" fill="currentColor" aria-hidden="true">
                        <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8Z" />
                    </svg>
                    <span dir="ltr">mostfaswaisi93</span>
                </a>
            </div>
        </footer>
    </div>

    {{-- The one shared tooltip for the header controls. Fixed, and rendered LAST
         in the body so it wins on DOM order - the shell sits inside `.page-enter`,
         whose fill-mode:both animation leaves a transform in place and therefore
         its own stacking context, which would paint over a bubble declared
         higher up. Only the `below` placement is bound; see the x-data above. --}}
    <div x-show="tipText !== ''" x-cloak
        class="pointer-events-none fixed z-[90] whitespace-nowrap rounded-lg bg-inverse-surface px-3 py-1.5 font-body-sm text-body-sm font-bold text-inverse-on-surface shadow-xl"
        {{-- Physical `left`, which is already direction-correct off
             getBoundingClientRect, so it centres under the control in both dirs. --}}
        :style="`top:${tipTop}px; left:${tipLeft}px; transform:translateX(-50%);`"
        x-text="tipText" role="tooltip" style="display:none"></div>
</body>
</html>
