<!DOCTYPE html>
<html lang="ar" dir="rtl" x-data="{ dark: localStorage.getItem('theme') === 'dark' }" x-init="$watch('dark', v => { document.documentElement.classList.toggle('dark', v); localStorage.setItem('theme', v ? 'dark' : 'light') }); document.documentElement.classList.toggle('dark', dark)" :class="{ dark: dark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'حوالاتي')</title>

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
                <span class="font-display-lg text-2xl font-bold leading-none text-primary">حوالاتي</span>
            </a>
            <button
                type="button"
                @click="dark = !dark"
                class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                aria-label="تبديل المظهر">
                <span class="material-symbols-outlined" x-text="dark ? 'light_mode' : 'dark_mode'"></span>
            </button>
        </header>

        <main class="w-full flex-grow pt-16 lg:grid lg:grid-cols-2">
            @yield('content')
        </main>

        <footer class="z-20 flex flex-col items-center justify-between gap-3 border-t border-outline-variant bg-surface-container-lowest px-inline-md py-stack-md md:flex-row md:px-inline-lg">
            <div class="flex flex-col items-center gap-2 md:flex-row md:gap-4">
                <div class="flex items-center gap-2">
                    <x-logo />
                    <span class="font-headline-md text-headline-md text-primary">حوالاتي</span>
                </div>
                <span class="font-body-sm text-on-surface-variant">© 2026 حوالاتي. جميع الحقوق محفوظة.</span>
            </div>
            <nav class="flex flex-wrap justify-center gap-4">
                <a href="/privacy" class="cursor-pointer font-body-sm text-on-surface-variant transition-colors hover:text-secondary">سياسة الخصوصية</a>
                <a href="/terms" class="cursor-pointer font-body-sm text-on-surface-variant transition-colors hover:text-secondary">شروط الخدمة</a>
                <a href="/help" class="cursor-pointer font-body-sm text-on-surface-variant transition-colors hover:text-secondary">مركز المساعدة</a>
            </nav>
        </footer>
    </div>
</body>
</html>
