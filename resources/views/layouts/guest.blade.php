@php $otherLocale = app()->getLocale() === 'ar' ? 'en' : 'ar'; @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="lang-{{ app()->getLocale() }}"
    x-data="{ dark: localStorage.getItem('theme') === 'dark' }"
    x-init="$watch('dark', v => { document.documentElement.classList.toggle('dark', v); localStorage.setItem('theme', v ? 'dark' : 'light') }); document.documentElement.classList.toggle('dark', dark)"
    :class="{ dark: dark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('حوالاتي'))</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface font-sans text-on-surface antialiased">
    <div class="page-enter flex min-h-screen flex-col bg-surface text-on-surface">
        <header class="fixed inset-x-0 top-0 z-50 flex h-16 items-center justify-between border-b border-outline-variant bg-surface px-inline-md shadow-sm md:px-inline-lg">
            <a href="{{ route('login') }}" class="flex items-center gap-3">
                <x-logo />
                <span class="font-display-lg text-2xl font-bold leading-none text-primary">{{ __('حوالاتي') }}</span>
            </a>
            <div class="flex items-center gap-1">
                <a href="{{ route('locale.switch', $otherLocale) }}"
                    class="flex h-10 items-center gap-1.5 rounded-full px-3 text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                    aria-label="{{ __('تغيير اللغة') }}">
                    <span class="material-symbols-outlined">translate</span>
                    <span class="font-body-sm font-bold">{{ $otherLocale === 'en' ? 'EN' : 'ع' }}</span>
                </a>
                <button
                    type="button"
                    @click="dark = !dark"
                    class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                    aria-label="{{ __('تبديل المظهر') }}">
                    <span class="material-symbols-outlined" x-text="dark ? 'light_mode' : 'dark_mode'"></span>
                </button>
            </div>
        </header>

        <main class="w-full flex-grow pt-16 lg:grid lg:grid-cols-2">
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
                    <a href="/privacy" class="cursor-pointer font-body-sm text-on-surface-variant transition-colors hover:text-secondary">{{ __('سياسة الخصوصية') }}</a>
                    <a href="/terms" class="cursor-pointer font-body-sm text-on-surface-variant transition-colors hover:text-secondary">{{ __('شروط الخدمة') }}</a>
                    <a href="/help" class="cursor-pointer font-body-sm text-on-surface-variant transition-colors hover:text-secondary">{{ __('مركز المساعدة') }}</a>
                </nav>
                <a href="https://github.com/mostfaswaisi93" target="_blank" rel="noopener noreferrer"
                    class="flex items-center gap-1.5 rounded-lg px-2 py-1 font-body-sm text-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                    aria-label="{{ __('حساب GitHub للمطوّر') }}">
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
</body>
</html>
