@php
    $admin = auth('admin')->user();

    // Grouped so a 10-item rail reads as three short scannable blocks instead
    // of one long undifferentiated list. Group labels hide when collapsed.
    $navGroups = [
        [
            'label' => 'عام',
            'items' => [
                ['href' => route('admin.dashboard'), 'icon' => 'dashboard', 'label' => 'لوحة التحكم', 'match' => 'admin/dashboard'],
                ['href' => '/admin/licenses', 'icon' => 'key', 'label' => 'التراخيص', 'match' => 'admin/licenses*'],
                ['href' => '/admin/packages', 'icon' => 'inventory_2', 'label' => 'الباقات', 'match' => 'admin/packages*'],
            ],
        ],
        [
            'label' => 'المالية',
            'items' => [
                ['href' => '/admin/boxes', 'icon' => 'account_balance_wallet', 'label' => 'الصناديق', 'match' => 'admin/boxes*'],
                ['href' => '/admin/partners', 'icon' => 'handshake', 'label' => 'الشركاء', 'match' => 'admin/partners*'],
                ['href' => '/admin/payments', 'icon' => 'payments', 'label' => 'المدفوعات', 'match' => 'admin/payments*'],
                ['href' => '/admin/finance', 'icon' => 'assessment', 'label' => 'المالية', 'match' => 'admin/finance*'],
            ],
        ],
        [
            'label' => 'النظام',
            'items' => [
                ['href' => '/admin/backups', 'icon' => 'backup', 'label' => 'النسخ الاحتياطي', 'match' => 'admin/backups*'],
                ['href' => '/admin/logs', 'icon' => 'fact_check', 'label' => 'السجلات', 'match' => 'admin/logs*'],
                ['href' => '/admin/settings', 'icon' => 'settings', 'label' => 'الإعدادات', 'match' => 'admin/settings*'],
            ],
        ],
    ];
@endphp
@php $otherLocale = app()->getLocale() === 'ar' ? 'en' : 'ar'; @endphp
<!DOCTYPE html>
{{--
    `collapsed` drives the desktop icon-only rail and is persisted so it
    survives navigation. Every collapse-dependent class is gated behind `lg:`
    — on mobile the sidebar is an overlay that must always show full labels,
    so the collapsed state is deliberately inert below that breakpoint.
--}}
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="lang-{{ app()->getLocale() }}"
    x-data="{
        dark: localStorage.getItem('theme') === 'dark',
        sidebarOpen: false,
        collapsed: localStorage.getItem('adminSidebarCollapsed') === '1',
        confirmOpen: false, confirmMessage: '', confirmFormId: null, confirmTitle: '', confirmLabel: '',
    }"
    x-init="
        $watch('dark', v => { document.documentElement.classList.toggle('dark', v); localStorage.setItem('theme', v ? 'dark' : 'light') });
        document.documentElement.classList.toggle('dark', dark);
        $watch('collapsed', v => localStorage.setItem('adminSidebarCollapsed', v ? '1' : '0'));
    "
    :class="{ dark: dark }">
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
        class="fixed start-0 top-0 z-50 flex h-screen w-64 flex-col border-e border-outline-variant bg-surface-container-low py-stack-md shadow-sm transition-[transform,width] duration-300 lg:!translate-x-0"
        :class="[
            sidebarOpen ? 'translate-x-0' : '-translate-x-full rtl:translate-x-full',
            collapsed ? 'lg:w-[5.5rem]' : 'lg:w-64',
        ]">

        {{-- Brand + collapse toggle --}}
        <div class="mb-6 flex items-center gap-3 px-inline-md" :class="collapsed && 'lg:justify-center lg:px-2'">
            <x-logo />
            <div class="min-w-0 flex-1" :class="collapsed && 'lg:hidden'">
                <span class="block font-display-lg text-2xl font-bold leading-tight text-primary">{{ __('حوالاتي') }}</span>
                <p class="font-body-sm text-body-sm text-on-surface-variant">{{ __('المشرف العام') }}</p>
            </div>
            <button type="button" @click="collapsed = !collapsed"
                class="hidden h-9 w-9 flex-shrink-0 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary lg:flex"
                :class="collapsed && 'lg:hidden'"
                :aria-label="collapsed ? '{{ __('توسيع القائمة') }}' : '{{ __('طي القائمة') }}'"
                :aria-expanded="(!collapsed).toString()">
                <span class="material-symbols-outlined !text-xl rtl:-scale-x-100">left_panel_close</span>
            </button>
        </div>

        {{-- When collapsed the toggle moves to its own centered row, so it stays
             reachable without the brand block stealing the width. --}}
        <div class="mb-2 hidden justify-center px-2" :class="collapsed ? 'lg:flex' : 'lg:hidden'">
            <button type="button" @click="collapsed = !collapsed"
                class="group relative flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                :aria-label="'{{ __('توسيع القائمة') }}'">
                <span class="material-symbols-outlined !text-xl rtl:-scale-x-100">left_panel_open</span>
                <span class="pointer-events-none absolute start-full z-[60] ms-3 hidden whitespace-nowrap rounded-lg bg-inverse-surface px-3 py-1.5 font-body-sm text-body-sm font-bold text-inverse-on-surface opacity-0 shadow-xl transition-opacity duration-150 group-hover:opacity-100 lg:block">
                    {{ __('توسيع القائمة') }}
                </span>
            </button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-inline-md" :class="collapsed && 'lg:px-2'">
            @foreach ($navGroups as $group)
                <p class="px-4 pb-1 pt-4 font-label-caps text-label-caps uppercase tracking-wider text-outline"
                    :class="collapsed && 'lg:hidden'">{{ __($group['label']) }}</p>

                {{-- Collapsed: a hairline stands in for the hidden group heading so
                     the three blocks stay visually separated in the icon rail. --}}
                <div class="mx-3 hidden border-t border-outline-variant/60" :class="collapsed ? 'lg:mt-3 lg:block' : 'lg:hidden'"></div>

                @foreach ($group['items'] as $item)
                    @php $isActive = request()->is($item['match']); @endphp
                    <a
                        href="{{ $item['href'] }}"
                        @if ($isActive) aria-current="page" @endif
                        class="group relative flex items-center gap-3 rounded-lg border-s-4 px-4 py-3 font-body-lg text-body-lg transition-all duration-200 {{ $isActive ? 'border-primary bg-surface-container-high font-bold text-primary' : 'border-transparent text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface' }}"
                        :class="collapsed && 'lg:justify-center lg:gap-0 lg:border-s-0 lg:border-e-4 lg:px-0'">
                        <span class="material-symbols-outlined flex-shrink-0" @if ($isActive) data-weight="fill" @endif>{{ $item['icon'] }}</span>
                        <span class="truncate" :class="collapsed && 'lg:hidden'">{{ __($item['label']) }}</span>

                        {{-- Tooltip: only rendered as visible in the collapsed desktop rail,
                             where the label is gone. `start-full` resolves to the inline-end
                             edge, so it flips sides automatically under RTL. --}}
                        <span
                            class="pointer-events-none absolute start-full top-1/2 z-[60] ms-3 hidden -translate-y-1/2 whitespace-nowrap rounded-lg bg-inverse-surface px-3 py-1.5 font-body-sm text-body-sm font-bold text-inverse-on-surface opacity-0 shadow-xl transition-opacity duration-150 group-hover:opacity-100 group-focus-visible:opacity-100"
                            :class="collapsed ? 'lg:block' : ''"
                            role="tooltip">
                            {{ __($item['label']) }}
                        </span>
                    </a>
                @endforeach
            @endforeach
        </nav>

        <div class="mt-auto border-t border-outline-variant px-inline-md pt-6" :class="collapsed && 'lg:px-2'">
            <div class="flex items-center gap-3 px-2" :class="collapsed && 'lg:flex-col lg:gap-2 lg:px-0'">
                <div class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-full bg-primary-container font-bold text-on-primary-container">
                    {{ $admin ? mb_substr($admin->name, 0, 1) : '?' }}
                </div>
                <div class="min-w-0 flex-1 overflow-hidden" :class="collapsed && 'lg:hidden'">
                    <p class="truncate font-body-sm text-body-sm font-bold">{{ $admin?->name }}</p>
                    <p class="truncate font-body-sm text-[11px] text-on-surface-variant">{{ $admin?->email }}</p>
                </div>
                {{-- Confirmed: a stray click here ends the admin session outright. --}}
                <form method="POST" action="{{ route('admin.logout') }}" id="admin-logout-form">@csrf</form>
                <button
                    type="button"
                    @click="confirmOpen = true; confirmMessage = '{{ __('هل تريد تسجيل الخروج من حسابك؟') }}'; confirmFormId = 'admin-logout-form'; confirmTitle = '{{ __('تأكيد تسجيل الخروج') }}'; confirmLabel = '{{ __('تسجيل الخروج') }}'"
                    class="group relative flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error"
                    aria-label="{{ __('تسجيل الخروج') }}">
                    <span class="material-symbols-outlined rtl:-scale-x-100">logout</span>
                    <span
                        class="pointer-events-none absolute start-full bottom-1/2 z-[60] ms-3 hidden translate-y-1/2 whitespace-nowrap rounded-lg bg-inverse-surface px-3 py-1.5 font-body-sm text-body-sm font-bold text-inverse-on-surface opacity-0 shadow-xl transition-opacity duration-150 group-hover:opacity-100"
                        :class="collapsed ? 'lg:block' : ''"
                        role="tooltip">
                        {{ __('تسجيل الخروج') }}
                    </span>
                </button>
            </div>
        </div>
    </aside>

    <div class="flex min-h-screen flex-col transition-[margin] duration-300" :class="collapsed ? 'lg:ms-[5.5rem]' : 'lg:ms-64'">
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

    {{-- Layout-level so the sidebar's logout can reach it from any page. Pages
         with their own delete confirmations declare a nested x-data that
         shadows these roots, so the two never collide. --}}
    <x-confirm-modal />
</body>
</html>
