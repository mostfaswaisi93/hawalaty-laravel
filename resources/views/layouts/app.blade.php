@php
    $member = auth('tenant')->user();

    // Mirrors D:\Angular\Hawalaty\src\app\layout\layout.ts's `nav` array,
    // extended with the Devices / Activity Log / Help entries this task's
    // brief calls out explicitly (the current Angular sidebar doesn't
    // surface those three as nav items, only as their own routes — matching
    // the brief over the current Angular snapshot per this task's
    // instructions). `permission: null` means always visible, exactly like
    // Dashboard/History/Reports/Settings/Help in the Angular version.
    $navItems = [
        ['href' => route('dashboard'), 'icon' => 'dashboard', 'label' => 'لوحة التحكم', 'match' => 'dashboard', 'permission' => null],
        ['href' => '/transfers', 'icon' => 'send', 'label' => 'طلب جديد', 'match' => 'transfers*', 'permission' => \App\Enums\PermissionKey::TransfersCreate],
        ['href' => '/history', 'icon' => 'history', 'label' => 'السجل', 'match' => 'history*', 'permission' => null],
        ['href' => '/customers', 'icon' => 'contacts', 'label' => 'العملاء', 'match' => 'customers*', 'permission' => null],
        ['href' => '/banks', 'icon' => 'account_balance', 'label' => 'البنوك والمحافظ', 'match' => 'banks*', 'permission' => \App\Enums\PermissionKey::BanksManage],
        ['href' => '/menu', 'icon' => 'restaurant_menu', 'label' => 'قائمة الطعام', 'match' => 'menu*', 'permission' => \App\Enums\PermissionKey::MenuManage],
        ['href' => '/expenses', 'icon' => 'payments', 'label' => 'المصروفات', 'match' => 'expenses*', 'permission' => \App\Enums\PermissionKey::ExpensesManage],
        ['href' => '/reports', 'icon' => 'assessment', 'label' => 'التقارير', 'match' => 'reports*', 'permission' => null],
        ['href' => '/constants', 'icon' => 'tune', 'label' => 'الثوابت', 'match' => 'constants*', 'permission' => \App\Enums\PermissionKey::ConstantsManage],
        ['href' => '/team', 'icon' => 'group', 'label' => 'الفريق', 'match' => 'team*', 'permission' => \App\Enums\PermissionKey::TeamManage],
        ['href' => '/devices', 'icon' => 'devices', 'label' => 'الأجهزة', 'match' => 'devices*', 'permission' => \App\Enums\PermissionKey::DevicesManage],
        ['href' => '/activity-log', 'icon' => 'fact_check', 'label' => 'سجل النشاطات', 'match' => 'activity-log*', 'permission' => null],
        ['href' => '/settings', 'icon' => 'settings', 'label' => 'الإعدادات', 'match' => 'settings*', 'permission' => null],
        ['href' => '/help', 'icon' => 'help', 'label' => 'المساعدة', 'match' => 'help*', 'permission' => null],
    ];

    $visibleNav = array_filter($navItems, fn ($item) => ! $item['permission'] || $member?->hasPermission($item['permission']));
@endphp
@php $otherLocale = app()->getLocale() === 'ar' ? 'en' : 'ar'; @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="lang-{{ app()->getLocale() }}" x-data="{ dark: localStorage.getItem('theme') === 'dark', sidebarOpen: false }" x-init="$watch('dark', v => { document.documentElement.classList.toggle('dark', v); localStorage.setItem('theme', v ? 'dark' : 'light') }); document.documentElement.classList.toggle('dark', dark)" :class="{ dark: dark }">
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

    <div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-40 bg-black/30 lg:hidden" @click="sidebarOpen = false" style="display:none"></div>

    <aside
        class="fixed start-0 top-0 z-50 flex h-screen w-64 flex-col border-e border-outline-variant bg-surface-container-low px-inline-md py-stack-md shadow-sm transition-transform duration-300 lg:!translate-x-0"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full rtl:translate-x-full'">
        <div class="mb-10 flex items-center gap-3 px-4">
            <x-logo />
            <div class="min-w-0">
                <span class="block font-display-lg text-2xl font-bold leading-tight text-primary">{{ __('حوالاتي') }}</span>
                <p class="font-body-sm text-body-sm text-on-surface-variant">My Transfers</p>
            </div>
        </div>

        <nav class="flex-1 space-y-2 overflow-y-auto">
            @foreach ($visibleNav as $item)
                @php $isActive = request()->is($item['match']); @endphp
                <a
                    href="{{ $item['href'] }}"
                    class="flex items-center gap-3 rounded-lg border-s-4 px-4 py-3 font-body-lg text-body-lg transition-all duration-200 {{ $isActive ? 'border-primary bg-surface-container-high font-bold text-primary' : 'border-transparent text-on-surface-variant hover:bg-surface-container-highest' }}">
                    <span class="material-symbols-outlined {{ $item['icon'] === 'send' ? 'rtl:-scale-x-100' : '' }}" @if ($isActive) data-weight="fill" @endif>{{ $item['icon'] }}</span>
                    <span>{{ __($item['label']) }}</span>
                </a>
            @endforeach
        </nav>

        <div class="mt-auto border-t border-outline-variant pt-6">
            @if ($member?->hasPermission(\App\Enums\PermissionKey::TransfersCreate))
                <a
                    href="/transfers"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-secondary-container px-4 py-3 font-bold text-on-secondary-fixed shadow-lg transition-transform active:scale-95">
                    <span class="material-symbols-outlined">add</span>
                    <span>{{ __('طلب جديد') }}</span>
                </a>
            @endif

            <div class="mt-6 flex items-center gap-3 px-2">
                <div class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-full bg-primary-container font-bold text-on-primary-container">
                    {{ $member ? mb_substr($member->name, 0, 1) : '?' }}
                </div>
                <div class="min-w-0 flex-1 overflow-hidden">
                    <p class="truncate text-sm font-bold">{{ $member?->name }}</p>
                    <p class="truncate text-xs text-on-surface-variant">{{ $member?->email }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
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
            <div class="flex flex-1 items-center gap-3">
                <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary lg:hidden" @click="sidebarOpen = !sidebarOpen" aria-label="{{ __('القائمة') }}">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <div class="search-pill hidden w-full max-w-md items-center gap-2 rounded-full bg-surface-container px-4 py-1.5 transition-shadow sm:flex">
                    <span class="material-symbols-outlined text-outline">search</span>
                    <input type="text" aria-label="{{ __('بحث عن معاملة أو مستلم') }}" placeholder="{{ __('بحث عن معاملة أو مستلم...') }}" class="w-full border-none bg-transparent px-2 font-body-sm text-sm outline-none focus:ring-0" />
                </div>
            </div>

            <div class="flex items-center gap-4 md:gap-6">
                <div class="flex items-center gap-1 border-s border-outline-variant ps-4 md:ps-6">
                    <a href="{{ route('locale.switch', $otherLocale) }}" class="flex h-10 items-center gap-1.5 rounded-full px-3 text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تغيير اللغة') }}">
                        <span class="material-symbols-outlined">translate</span>
                        <span class="font-body-sm font-bold">{{ $otherLocale === 'en' ? 'EN' : 'ع' }}</span>
                    </a>
                    <button type="button" @click="dark = !dark" class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تبديل المظهر') }}">
                        <span class="material-symbols-outlined" x-text="dark ? 'light_mode' : 'dark_mode'"></span>
                    </button>
                    <a href="/help" class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('المساعدة') }}">
                        <span class="material-symbols-outlined">help</span>
                    </a>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-[1680px] flex-1 p-inline-lg">
            <x-flash-messages />
            @yield('content')
        </main>

        <footer class="mt-auto border-t border-outline-variant/40 p-6 text-center text-body-sm text-outline">
            <p>{{ __('© 2026 حوالاتي. جميع الحقوق محفوظة. مرخّص من قبل البنك المركزي.') }}</p>
        </footer>
    </div>
</body>
</html>
