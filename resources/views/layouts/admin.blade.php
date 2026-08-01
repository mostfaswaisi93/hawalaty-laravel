@php
    $admin = auth('admin')->user();

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
                ['href' => '/admin/settlements', 'icon' => 'balance', 'label' => 'المقاصة', 'match' => 'admin/settlements*'],
            ],
        ],
        [
            'label' => 'النظام',
            'items' => [
                ['href' => '/admin/contact-messages', 'icon' => 'mail', 'label' => 'رسائل التواصل', 'match' => 'admin/contact-messages*'],
                ['href' => '/admin/backups', 'icon' => 'backup', 'label' => 'النسخ الاحتياطي', 'match' => 'admin/backups*'],
                ['href' => '/admin/logs', 'icon' => 'fact_check', 'label' => 'السجلات', 'match' => 'admin/logs*'],
                ['href' => '/admin/settings', 'icon' => 'settings', 'label' => 'الإعدادات', 'match' => 'admin/settings*'],
            ],
        ],
    ];

    $currentSection = null;
    $currentGroup = null;
    foreach ($navGroups as $group) {
        foreach ($group['items'] as $item) {
            if (request()->is($item['match'])) {
                $currentSection = $item;
                $currentGroup = $group['label'];
                break 2;
            }
        }
    }

    $otherLocale = app()->getLocale() === 'ar' ? 'en' : 'ar';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="lang-{{ app()->getLocale() }}"
    x-data="{
        dark: localStorage.getItem('theme') === 'dark',
        sidebarOpen: false,
        collapsed: localStorage.getItem('adminSidebarCollapsed') === '1',
        confirmOpen: false, confirmMessage: '', confirmFormId: null, confirmTitle: '', confirmLabel: '',
        tipText: '', tipTop: 0, tipLeft: 0, tipPlacement: 'side',

        showTip(el, text) {
            if (!window.matchMedia('(min-width: 1024px)').matches) return;
            const r = el.getBoundingClientRect();
            this.tipTop = r.top + r.height / 2;
            this.tipPlacement = 'side';
            this.tipText = text;
        },

        showTipBelow(el, text) {

            if (!window.matchMedia('(pointer: fine)').matches) return;
            const r = el.getBoundingClientRect();
            this.tipTop = r.bottom + 10;
            this.tipLeft = r.left + r.width / 2;
            this.tipPlacement = 'below';
            this.tipText = text;
        },
        hideTip() { this.tipText = ''; },
    }"
    x-init="
        $watch('dark', v => { document.documentElement.classList.toggle('dark', v); localStorage.setItem('theme', v ? 'dark' : 'light') });
        document.documentElement.classList.toggle('dark', dark);
        $watch('collapsed', v => { localStorage.setItem('adminSidebarCollapsed', v ? '1' : '0'); tipText = '' });
    "
    :class="{ dark: dark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('لوحة التحكم')) · {{ app(\App\Settings\PlatformSettings::class)->get('site_name') }}</title>

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <meta name="theme-color" content="#00236f">
    <x-fonts />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface font-sans text-on-surface antialiased">
    <x-skip-link />

    <div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-40 bg-black/30 lg:hidden" @click="sidebarOpen = false" style="display:none"></div>

    <aside
        class="fixed start-0 top-0 z-50 flex h-screen w-64 flex-col border-e border-sidebar-border bg-sidebar py-stack-md shadow-xl transition-[transform,width] duration-300 lg:!translate-x-0"
        :class="[
            sidebarOpen ? 'translate-x-0' : '-translate-x-full rtl:translate-x-full',
            collapsed ? 'lg:w-[5.5rem]' : 'lg:w-64',
        ]">

        <div class="mb-6 flex items-center gap-3 px-inline-md" :class="collapsed && 'lg:justify-center lg:px-2'">
            <a href="{{ route('admin.dashboard') }}"
                @mouseenter="showTip($el, @js(__('حوالاتي')))" @mouseleave="hideTip()"
                @focus="showTip($el, @js(__('حوالاتي')))" @blur="hideTip()"
                class="flex-shrink-0 rounded-xl transition-transform duration-200 hover:scale-105 active:scale-95"
                aria-label="{{ __('حوالاتي') }}" title="{{ __('حوالاتي') }}">
                <x-logo />
            </a>
            <div class="min-w-0 flex-1" :class="collapsed && 'lg:hidden'">
                <span class="block font-display-lg text-2xl font-bold leading-tight text-on-sidebar">{{ __('حوالاتي') }}</span>
                <p class="font-body-sm text-body-sm text-on-sidebar-muted">{{ __('المشرف العام') }}</p>
            </div>
            <button type="button" @click="collapsed = !collapsed"
                @mouseenter="showTip($el, @js(__('طي القائمة')))" @mouseleave="hideTip()"
                @focus="showTip($el, @js(__('طي القائمة')))" @blur="hideTip()"
                class="hidden h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl border border-sidebar-border bg-sidebar-hover text-on-sidebar-muted shadow-sm transition-all duration-200 hover:border-sidebar-accent hover:bg-sidebar-accent hover:text-sidebar hover:shadow-md active:scale-90 lg:flex"
                :class="collapsed && 'lg:hidden'"
                aria-label="{{ __('طي القائمة') }}" title="{{ __('طي القائمة') }}" :aria-expanded="(!collapsed).toString()">
                <span class="material-symbols-outlined !text-xl rtl:-scale-x-100">left_panel_close</span>
            </button>
        </div>

        <div class="mb-2 hidden justify-center px-2" :class="collapsed ? 'lg:flex' : 'lg:hidden'">
            <button type="button" @click="collapsed = !collapsed"
                @mouseenter="showTip($el, @js(__('توسيع القائمة')))" @mouseleave="hideTip()"
                @focus="showTip($el, @js(__('توسيع القائمة')))" @blur="hideTip()"
                class="flex h-10 w-10 items-center justify-center rounded-xl border border-sidebar-border bg-sidebar-hover text-on-sidebar-muted shadow-sm transition-all duration-200 hover:border-sidebar-accent hover:bg-sidebar-accent hover:text-sidebar hover:shadow-md active:scale-90"
                aria-label="{{ __('توسيع القائمة') }}" title="{{ __('توسيع القائمة') }}">
                <span class="material-symbols-outlined !text-xl rtl:-scale-x-100">left_panel_open</span>
            </button>
        </div>

        <nav class="flex-1 scroll-smooth space-y-1 overflow-y-auto overflow-x-hidden px-inline-md" :class="collapsed && 'lg:px-2'">
            @foreach ($navGroups as $group)
                <p class="px-4 pb-1 pt-4 font-label-caps text-label-caps uppercase tracking-wider text-on-sidebar-muted"
                    :class="collapsed && 'lg:hidden'">{{ __($group['label']) }}</p>

                <div class="mx-3 hidden border-t border-sidebar-border" :class="collapsed ? 'lg:mt-3 lg:block' : 'lg:hidden'"></div>

                @foreach ($group['items'] as $item)
                    @php $isActive = request()->is($item['match']); @endphp
                    <a
                        href="{{ $item['href'] }}"
                        @if ($isActive) aria-current="page" @endif
                        @mouseenter="showTip($el, @js(__($item['label'])))" @mouseleave="hideTip()"
                        @focus="showTip($el, @js(__($item['label'])))" @blur="hideTip()"
                        class="flex items-center gap-3 rounded-lg border-s-4 px-4 py-3 font-body-lg text-body-lg transition-all duration-200 {{ $isActive ? 'border-sidebar-accent bg-sidebar-active font-bold text-white' : 'border-transparent text-on-sidebar-muted hover:bg-sidebar-hover hover:text-on-sidebar' }}"
                        :class="collapsed && 'lg:justify-center lg:gap-0 lg:px-0'">
                        <span class="material-symbols-outlined flex-shrink-0" @if ($isActive) data-weight="fill" @endif>{{ $item['icon'] }}</span>
                        <span class="truncate" :class="collapsed && 'lg:hidden'">{{ __($item['label']) }}</span>
                    </a>
                @endforeach
            @endforeach
        </nav>

        <div class="mt-auto border-t border-sidebar-border px-inline-md pt-4" :class="collapsed && 'lg:px-2'">
            <a href="{{ route('admin.profile.index') }}"
                @mouseenter="showTip($el, @js($admin?->name ?? ''))" @mouseleave="hideTip()"
                @focus="showTip($el, @js($admin?->name ?? ''))" @blur="hideTip()"
                class="flex items-center gap-3 rounded-xl p-2 transition-colors hover:bg-sidebar-hover"
                :class="collapsed && 'lg:justify-center lg:p-1'"
                title="{{ __('الملف الشخصي') }}">
                <span class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-full bg-sidebar-accent/15 font-bold text-sidebar-accent ring-1 ring-sidebar-accent/30">
                    {{ $admin ? mb_substr($admin->name, 0, 1) : '?' }}
                </span>
                <span class="min-w-0 flex-1" :class="collapsed && 'lg:hidden'">
                    <span class="block truncate text-start font-body-sm text-body-sm font-bold text-on-sidebar"><span dir="auto">{{ $admin?->name }}</span></span>
                    <span class="block truncate text-start font-body-sm text-[11px] text-on-sidebar-muted"><span dir="ltr">{{ $admin?->email }}</span></span>
                </span>
            </a>

            <form method="POST" action="{{ route('admin.logout') }}" id="admin-logout-form">@csrf</form>
            <button
                type="button"
                @click="confirmOpen = true; confirmMessage = '{{ __('هل تريد تسجيل الخروج من حسابك؟') }}'; confirmFormId = 'admin-logout-form'; confirmTitle = '{{ __('تأكيد تسجيل الخروج') }}'; confirmLabel = '{{ __('تسجيل الخروج') }}'"
                @mouseenter="showTip($el, @js(__('تسجيل الخروج')))" @mouseleave="hideTip()"
                @focus="showTip($el, @js(__('تسجيل الخروج')))" @blur="hideTip()"
                class="mt-1 flex w-full items-center gap-3 rounded-xl px-2 py-2.5 font-body-sm font-bold text-on-sidebar-muted transition-colors hover:bg-error/20 hover:text-error"
                :class="collapsed && 'lg:justify-center lg:gap-0 lg:px-0'"
                aria-label="{{ __('تسجيل الخروج') }}" title="{{ __('تسجيل الخروج') }}">
                <span class="material-symbols-outlined flex-shrink-0 rtl:-scale-x-100">logout</span>
                <span :class="collapsed && 'lg:hidden'">{{ __('تسجيل الخروج') }}</span>
            </button>
        </div>
    </aside>

    <div class="flex min-h-screen flex-col transition-[margin] duration-300" :class="collapsed ? 'lg:ms-[5.5rem]' : 'lg:ms-64'">
        <header class="sticky top-0 z-30 flex h-16 w-full items-center justify-between gap-4 border-b border-outline-variant bg-surface/85 px-inline-lg backdrop-blur">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary lg:hidden" @click="hideTip(); sidebarOpen = !sidebarOpen"
                    @mouseenter="showTipBelow($el, @js(__('القائمة')))" @mouseleave="hideTip()"
                    @focus="showTipBelow($el, @js(__('القائمة')))" @blur="hideTip()"
                    aria-label="{{ __('القائمة') }}" title="{{ __('القائمة') }}">
                    <span class="material-symbols-outlined">menu</span>
                </button>

                @if ($currentSection)
                    <nav class="flex min-w-0 items-center gap-2" aria-label="{{ __('مسار التنقل') }}">
                        <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <span class="material-symbols-outlined !text-xl">{{ $currentSection['icon'] }}</span>
                        </span>
                        <span class="min-w-0">
                            <span class="hidden font-label-caps text-label-caps uppercase tracking-wider text-outline sm:block">{{ __($currentGroup) }}</span>
                            <span class="block truncate font-headline-md text-body-lg font-bold text-on-surface">{{ __($currentSection['label']) }}</span>
                        </span>
                    </nav>
                @endif
            </div>

            <div class="flex flex-shrink-0 items-center gap-1">
                <span class="me-2 hidden font-body-sm text-body-sm text-on-surface-variant xl:inline">
                    {{ now()->translatedFormat('l، j F Y') }}
                </span>

                <a href="{{ route('admin.dashboard') }}"
                    @mouseenter="showTipBelow($el, @js(__('لوحة التحكم')))" @mouseleave="hideTip()"
                    @focus="showTipBelow($el, @js(__('لوحة التحكم')))" @blur="hideTip()"
                    class="hidden h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary sm:flex"
                    aria-label="{{ __('لوحة التحكم') }}" title="{{ __('لوحة التحكم') }}">
                    <span class="material-symbols-outlined">space_dashboard</span>
                </a>

                <a href="{{ route('locale.switch', $otherLocale) }}"
                    @mouseenter="showTipBelow($el, @js(__('تغيير اللغة')))" @mouseleave="hideTip()"
                    @focus="showTipBelow($el, @js(__('تغيير اللغة')))" @blur="hideTip()"
                    class="flex h-10 min-w-10 items-center justify-center rounded-full px-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                    aria-label="{{ __('تغيير اللغة') }}" title="{{ __('تغيير اللغة') }}">
                    {{ $otherLocale === 'en' ? 'EN' : 'ع' }}
                </a>

                <button type="button" @click="dark = !dark"
                    @mouseenter="showTipBelow($el, dark ? @js(__('الوضع الفاتح')) : @js(__('الوضع الداكن')))" @mouseleave="hideTip()"
                    @focus="showTipBelow($el, dark ? @js(__('الوضع الفاتح')) : @js(__('الوضع الداكن')))" @blur="hideTip()"
                    class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                    :aria-label="dark ? '{{ __('الوضع الفاتح') }}' : '{{ __('الوضع الداكن') }}'">
                    <span class="material-symbols-outlined" x-text="dark ? 'light_mode' : 'dark_mode'"></span>
                </button>

                <div class="relative ms-1" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                    <button type="button" @click="open = !open"
                        @mouseenter="showTipBelow($el, @js(__('حساب المشرف')))" @mouseleave="hideTip()"
                        class="flex items-center gap-2 rounded-full p-1 transition-colors hover:bg-surface-container-high"
                        :aria-expanded="open.toString()" aria-haspopup="menu"
                        aria-label="{{ __('حساب المشرف') }}" title="{{ __('حساب المشرف') }}">
                        <span class="grid h-9 w-9 flex-shrink-0 place-items-center rounded-full bg-primary-container font-bold text-on-primary-container">
                            {{ $admin ? mb_substr($admin->name, 0, 1) : '?' }}
                        </span>
                        <span class="material-symbols-outlined !text-lg text-on-surface-variant transition-transform" :class="open && 'rotate-180'">expand_more</span>
                    </button>

                    <div x-show="open" x-cloak style="display:none"
                        class="absolute end-0 top-12 z-50 w-72 overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                        role="menu">
                        <div class="flex items-center gap-3 border-b border-outline-variant/60 bg-surface-container-low p-3">
                            <span class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-full bg-primary-container font-body-lg text-base font-bold text-on-primary-container">
                                {{ $admin ? mb_substr($admin->name, 0, 1) : '?' }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-start font-body-sm text-body-sm font-bold text-on-surface"><span dir="auto">{{ $admin?->name }}</span></span>
                                <span class="block truncate text-start font-body-sm text-[11px] text-on-surface-variant"><span dir="ltr">{{ $admin?->email }}</span></span>
                            </span>
                        </div>

                        <div class="p-1.5">
                            <a href="{{ route('admin.profile.index') }}" role="menuitem"
                                class="group flex items-center gap-3 rounded-xl px-2.5 py-2 font-body-sm text-body-sm font-bold text-on-surface-variant transition-colors hover:bg-primary/10 hover:text-primary">
                                <span class="grid h-8 w-8 flex-shrink-0 place-items-center rounded-lg bg-surface-container text-on-surface-variant transition-colors group-hover:bg-primary group-hover:text-on-primary">
                                    <span class="material-symbols-outlined !text-xl">person</span>
                                </span>
                                <span class="flex-1">{{ __('الملف الشخصي') }}</span>
                                <span class="material-symbols-outlined !text-lg opacity-0 transition-opacity group-hover:opacity-100 rtl:-scale-x-100">chevron_right</span>
                            </a>

                            <a href="{{ route('admin.settings.index') }}" role="menuitem"
                                class="group flex items-center gap-3 rounded-xl px-2.5 py-2 font-body-sm text-body-sm font-bold text-on-surface-variant transition-colors hover:bg-primary/10 hover:text-primary">
                                <span class="grid h-8 w-8 flex-shrink-0 place-items-center rounded-lg bg-surface-container text-on-surface-variant transition-colors group-hover:bg-primary group-hover:text-on-primary">
                                    <span class="material-symbols-outlined !text-xl">settings</span>
                                </span>
                                <span class="flex-1">{{ __('الإعدادات') }}</span>
                                <span class="material-symbols-outlined !text-lg opacity-0 transition-opacity group-hover:opacity-100 rtl:-scale-x-100">chevron_right</span>
                            </a>

                            <div class="my-1 border-t border-outline-variant/60"></div>

                            <button type="button" role="menuitem"
                                @click="open = false; confirmOpen = true; confirmMessage = '{{ __('هل تريد تسجيل الخروج من حسابك؟') }}'; confirmFormId = 'admin-logout-form'; confirmTitle = '{{ __('تأكيد تسجيل الخروج') }}'; confirmLabel = '{{ __('تسجيل الخروج') }}'"
                                class="group flex w-full items-center gap-3 rounded-xl px-2.5 py-2 font-body-sm text-body-sm font-bold text-error transition-colors hover:bg-error/10">
                                <span class="grid h-8 w-8 flex-shrink-0 place-items-center rounded-lg bg-error/10 text-error transition-colors group-hover:bg-error group-hover:text-on-error">
                                    <span class="material-symbols-outlined !text-xl rtl:-scale-x-100">logout</span>
                                </span>
                                <span class="flex-1 text-start">{{ __('تسجيل الخروج') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main id="main-content" tabindex="-1" class="w-full flex-1 p-inline-lg focus:outline-none">
            <x-flash-messages />
            @yield('content')
        </main>

        <x-app-footer />
    </div>

    <x-confirm-modal />

    <div x-show="tipText !== ''" x-cloak
        class="pointer-events-none fixed z-[110] whitespace-nowrap rounded-lg bg-inverse-surface px-3 py-1.5 font-body-sm text-body-sm font-bold text-inverse-on-surface shadow-xl"
        :style="tipPlacement === 'side'
            ? `top:${tipTop}px; inset-inline-start:${collapsed ? '6rem' : '17rem'}; transform:translateY(-50%);`
            : `top:${tipTop}px; left:${tipLeft}px; transform:translateX(-50%);`"
        x-text="tipText" role="tooltip" style="display:none"></div>
</body>
</html>
