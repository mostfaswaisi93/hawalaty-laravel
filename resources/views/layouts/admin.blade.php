@php
    $admin = auth('admin')->user();

    $navItems = [
        ['href' => route('admin.dashboard'), 'icon' => 'dashboard', 'label' => 'لوحة التحكم', 'match' => 'admin/dashboard'],
        ['href' => '/admin/licenses', 'icon' => 'key', 'label' => 'التراخيص', 'match' => 'admin/licenses*'],
        ['href' => '/admin/packages', 'icon' => 'inventory_2', 'label' => 'الباقات', 'match' => 'admin/packages*'],
        ['href' => '/admin/boxes', 'icon' => 'account_balance_wallet', 'label' => 'الصناديق', 'match' => 'admin/boxes*'],
        ['href' => '/admin/partners', 'icon' => 'handshake', 'label' => 'الشركاء', 'match' => 'admin/partners*'],
        ['href' => '/admin/payments', 'icon' => 'payments', 'label' => 'المدفوعات', 'match' => 'admin/payments*'],
        ['href' => '/admin/finance', 'icon' => 'assessment', 'label' => 'المالية', 'match' => 'admin/finance*'],
        ['href' => '/admin/backups', 'icon' => 'backup', 'label' => 'النسخ الاحتياطي', 'match' => 'admin/backups*'],
        ['href' => '/admin/logs', 'icon' => 'fact_check', 'label' => 'السجلات', 'match' => 'admin/logs*'],
        ['href' => '/admin/settings', 'icon' => 'settings', 'label' => 'الإعدادات', 'match' => 'admin/settings*'],
    ];
@endphp
@php $otherLocale = app()->getLocale() === 'ar' ? 'en' : 'ar'; @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="lang-{{ app()->getLocale() }}" x-data="{ dark: localStorage.getItem('theme') === 'dark', sidebarOpen: false }" x-init="$watch('dark', v => { document.documentElement.classList.toggle('dark', v); localStorage.setItem('theme', v ? 'dark' : 'light') }); document.documentElement.classList.toggle('dark', dark)" :class="{ dark: dark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('لوحة التحكم — حوالاتي'))</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface font-sans text-on-surface antialiased">

    <div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-40 bg-black/30 lg:hidden" @click="sidebarOpen = false" style="display:none"></div>

    <aside
        class="fixed start-0 top-0 z-50 flex h-screen w-64 flex-col border-e border-outline-variant bg-surface-container-low px-inline-md py-stack-md shadow-sm transition-transform duration-300 lg:!translate-x-0"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full rtl:translate-x-full'">
        <div class="mb-10 flex items-center gap-3 px-4">
            <x-logo />
            <div class="min-w-0">
                <span class="block font-display-lg text-2xl font-bold leading-tight text-primary">{{ __('حوالاتي') }}</span>
                <p class="font-body-sm text-body-sm text-on-surface-variant">Super Admin</p>
            </div>
        </div>

        <nav class="flex-1 space-y-2 overflow-y-auto">
            @foreach ($navItems as $item)
                @php $isActive = request()->is($item['match']); @endphp
                <a
                    href="{{ $item['href'] }}"
                    class="flex items-center gap-3 rounded-lg border-s-4 px-4 py-3 font-body-lg text-body-lg transition-all duration-200 {{ $isActive ? 'border-primary bg-surface-container-high font-bold text-primary' : 'border-transparent text-on-surface-variant hover:bg-surface-container-highest' }}">
                    <span class="material-symbols-outlined" @if ($isActive) data-weight="fill" @endif>{{ $item['icon'] }}</span>
                    <span>{{ __($item['label']) }}</span>
                </a>
            @endforeach
        </nav>

        <div class="mt-auto border-t border-outline-variant pt-6">
            <div class="flex items-center gap-3 px-2">
                <div class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-full bg-primary-container font-bold text-on-primary-container">
                    {{ $admin ? mb_substr($admin->name, 0, 1) : '?' }}
                </div>
                <div class="min-w-0 flex-1 overflow-hidden">
                    <p class="truncate text-sm font-bold">{{ $admin?->name }}</p>
                    <p class="truncate text-xs text-on-surface-variant">{{ $admin?->email }}</p>
                </div>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-error"
                        aria-label="{{ __('تسجيل الخروج') }}">
                        <span class="material-symbols-outlined rtl:-scale-x-100">logout</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div class="flex min-h-screen flex-col lg:ms-64">
        <header class="sticky top-0 z-30 flex h-16 w-full items-center justify-between gap-4 border-b border-outline-variant bg-surface px-inline-lg">
            <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary lg:hidden" @click="sidebarOpen = !sidebarOpen" aria-label="{{ __('القائمة') }}">
                <span class="material-symbols-outlined">menu</span>
            </button>

            <div class="flex items-center gap-1">
                <a href="{{ route('locale.switch', $otherLocale) }}" class="flex h-10 items-center gap-1.5 rounded-full px-3 text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تغيير اللغة') }}">
                    <span class="material-symbols-outlined">translate</span>
                    <span class="font-body-sm font-bold">{{ $otherLocale === 'en' ? 'EN' : 'ع' }}</span>
                </a>
                <button type="button" @click="dark = !dark" class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تبديل المظهر') }}">
                    <span class="material-symbols-outlined" x-text="dark ? 'light_mode' : 'dark_mode'"></span>
                </button>
            </div>
        </header>

        <main class="mx-auto w-full max-w-[1680px] flex-1 p-inline-lg">
            <x-flash-messages />
            @yield('content')
        </main>

        <footer class="mt-auto border-t border-outline-variant/40 p-6 text-center text-body-sm text-outline">
            <p>{{ __('© 2026 حوالاتي — لوحة تحكم المنصّة.') }}</p>
        </footer>
    </div>
</body>
</html>
