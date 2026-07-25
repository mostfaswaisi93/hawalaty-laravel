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

    // Drives the header breadcrumb — resolved from the same nav table so the
    // two can never drift apart.
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
{{--
    `collapsed` drives the desktop icon-only rail and is persisted so it
    survives navigation. Every collapse-dependent class is gated behind `lg:`
    — on mobile the sidebar is an overlay that must always show full labels,
    so the collapsed state is deliberately inert below that breakpoint.

    `tipText`/`tipY` back a SINGLE shared, position-fixed tooltip. Per-item
    absolutely-positioned tooltips were tried first and were wrong: the nav is
    `overflow-y-auto`, and CSS computes the other axis of a non-visible
    overflow to `auto` too, so tooltips sticking out past the rail both got
    clipped and produced a horizontal scrollbar inside the sidebar. A fixed
    element rendered outside the scroll container escapes that entirely.
--}}
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="lang-{{ app()->getLocale() }}"
    x-data="{
        dark: localStorage.getItem('theme') === 'dark',
        sidebarOpen: false,
        collapsed: localStorage.getItem('adminSidebarCollapsed') === '1',
        confirmOpen: false, confirmMessage: '', confirmFormId: null, confirmTitle: '', confirmLabel: '',
        tipText: '', tipTop: 0, tipLeft: 0, tipPlacement: 'side',
        // Sidebar rail: sits beside the item, vertically centred on it. Shown in
        // BOTH states — essential when collapsed (no label at all), still useful
        // when expanded because long labels are `truncate`d. Desktop only, since
        // below lg the sidebar is a full-width overlay.
        showTip(el, text) {
            if (!window.matchMedia('(min-width: 1024px)').matches) return;
            const r = el.getBoundingClientRect();
            this.tipTop = r.top + r.height / 2;
            this.tipPlacement = 'side';
            this.tipText = text;
        },
        // Header icons: sits below the control, horizontally centred. Uses the
        // same styled bubble as the sidebar instead of the native `title`
        // attribute, which is slow to appear and cannot be themed.
        showTipBelow(el, text) {
            // Mouse affordance only — on touch, mouseenter fires on tap and the
            // bubble would linger with nothing to dismiss it.
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
            {{-- The mark doubles as the home link, so it gets the same hover
                 affordance and tooltip as every other icon in the rail. --}}
            <a href="{{ route('admin.dashboard') }}"
                @mouseenter="showTip($el, @js(__('حوالاتي')))" @mouseleave="hideTip()"
                @focus="showTip($el, @js(__('حوالاتي')))" @blur="hideTip()"
                class="flex-shrink-0 rounded-xl transition-transform duration-200 hover:scale-105 active:scale-95"
                aria-label="{{ __('حوالاتي') }}">
                <x-logo />
            </a>
            <div class="min-w-0 flex-1" :class="collapsed && 'lg:hidden'">
                <span class="block font-display-lg text-2xl font-bold leading-tight text-primary">{{ __('حوالاتي') }}</span>
                <p class="font-body-sm text-body-sm text-on-surface-variant">{{ __('المشرف العام') }}</p>
            </div>
            {{-- Given a container, border and fill-on-hover so it reads as a real
                 control rather than a stray glyph next to the brand. --}}
            <button type="button" @click="collapsed = !collapsed"
                @mouseenter="showTip($el, @js(__('طي القائمة')))" @mouseleave="hideTip()"
                @focus="showTip($el, @js(__('طي القائمة')))" @blur="hideTip()"
                class="hidden h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl border border-outline-variant bg-surface-container text-on-surface-variant shadow-sm transition-all duration-200 hover:border-primary hover:bg-primary hover:text-on-primary hover:shadow-md active:scale-90 lg:flex"
                :class="collapsed && 'lg:hidden'"
                aria-label="{{ __('طي القائمة') }}" title="{{ __('طي القائمة') }}" :aria-expanded="(!collapsed).toString()">
                <span class="material-symbols-outlined !text-xl rtl:-scale-x-100">left_panel_close</span>
            </button>
        </div>

        {{-- When collapsed the toggle moves to its own centered row so the brand
             block does not have to reserve width for it. --}}
        <div class="mb-2 hidden justify-center px-2" :class="collapsed ? 'lg:flex' : 'lg:hidden'">
            <button type="button" @click="collapsed = !collapsed"
                @mouseenter="showTip($el, @js(__('توسيع القائمة')))" @mouseleave="hideTip()"
                @focus="showTip($el, @js(__('توسيع القائمة')))" @blur="hideTip()"
                class="flex h-10 w-10 items-center justify-center rounded-xl border border-outline-variant bg-surface-container text-on-surface-variant shadow-sm transition-all duration-200 hover:border-primary hover:bg-primary hover:text-on-primary hover:shadow-md active:scale-90"
                aria-label="{{ __('توسيع القائمة') }}" title="{{ __('توسيع القائمة') }}">
                <span class="material-symbols-outlined !text-xl rtl:-scale-x-100">left_panel_open</span>
            </button>
        </div>

        {{-- overflow-x-hidden is explicit: overflow-y-auto alone would compute the
             x axis to `auto` and show a stray horizontal scrollbar. --}}
        <nav class="flex-1 space-y-1 overflow-y-auto overflow-x-hidden px-inline-md" :class="collapsed && 'lg:px-2'">
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
                        @mouseenter="showTip($el, @js(__($item['label'])))" @mouseleave="hideTip()"
                        @focus="showTip($el, @js(__($item['label'])))" @blur="hideTip()"
                        class="flex items-center gap-3 rounded-lg border-s-4 px-4 py-3 font-body-lg text-body-lg transition-all duration-200 {{ $isActive ? 'border-primary bg-surface-container-high font-bold text-primary' : 'border-transparent text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface' }}"
                        :class="collapsed && 'lg:justify-center lg:gap-0 lg:border-s-0 lg:border-e-4 lg:px-0'">
                        <span class="material-symbols-outlined flex-shrink-0" @if ($isActive) data-weight="fill" @endif>{{ $item['icon'] }}</span>
                        <span class="truncate" :class="collapsed && 'lg:hidden'">{{ __($item['label']) }}</span>
                    </a>
                @endforeach
            @endforeach
        </nav>

        {{-- Identity and sign-out are stacked rather than crammed into one row:
             sharing the row with the logout button left the name ~110px and it
             truncated to "...tform Admin". On its own row it gets the full rail. --}}
        <div class="mt-auto border-t border-outline-variant px-inline-md pt-4" :class="collapsed && 'lg:px-2'">
            <a href="{{ route('admin.profile.index') }}"
                @mouseenter="showTip($el, @js($admin?->name ?? ''))" @mouseleave="hideTip()"
                @focus="showTip($el, @js($admin?->name ?? ''))" @blur="hideTip()"
                class="flex items-center gap-3 rounded-xl p-2 transition-colors hover:bg-surface-container-high"
                :class="collapsed && 'lg:justify-center lg:p-1'"
                title="{{ __('الملف الشخصي') }}">
                <span class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-full bg-primary-container font-bold text-on-primary-container">
                    {{ $admin ? mb_substr($admin->name, 0, 1) : '?' }}
                </span>
                {{-- dir=auto so a Latin name ellipsises at its own end rather than
                     having the RTL container push the "…" to the visual start. --}}
                <span class="min-w-0 flex-1" :class="collapsed && 'lg:hidden'">
                    <span class="block truncate font-body-sm text-body-sm font-bold text-on-surface" dir="auto">{{ $admin?->name }}</span>
                    <span class="block truncate font-body-sm text-[11px] text-on-surface-variant" dir="ltr">{{ $admin?->email }}</span>
                </span>
            </a>

            {{-- Confirmed: a stray click here ends the admin session outright. --}}
            <form method="POST" action="{{ route('admin.logout') }}" id="admin-logout-form">@csrf</form>
            <button
                type="button"
                @click="confirmOpen = true; confirmMessage = '{{ __('هل تريد تسجيل الخروج من حسابك؟') }}'; confirmFormId = 'admin-logout-form'; confirmTitle = '{{ __('تأكيد تسجيل الخروج') }}'; confirmLabel = '{{ __('تسجيل الخروج') }}'"
                @mouseenter="showTip($el, @js(__('تسجيل الخروج')))" @mouseleave="hideTip()"
                @focus="showTip($el, @js(__('تسجيل الخروج')))" @blur="hideTip()"
                class="mt-1 flex w-full items-center gap-3 rounded-xl px-2 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error"
                :class="collapsed && 'lg:justify-center lg:gap-0 lg:px-0'"
                aria-label="{{ __('تسجيل الخروج') }}">
                <span class="material-symbols-outlined flex-shrink-0 rtl:-scale-x-100">logout</span>
                <span :class="collapsed && 'lg:hidden'">{{ __('تسجيل الخروج') }}</span>
            </button>
        </div>
    </aside>

    <div class="flex min-h-screen flex-col transition-[margin] duration-300" :class="collapsed ? 'lg:ms-[5.5rem]' : 'lg:ms-64'">
        <header class="sticky top-0 z-30 flex h-16 w-full items-center justify-between gap-4 border-b border-outline-variant bg-surface/85 px-inline-lg backdrop-blur">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary lg:hidden" @click="sidebarOpen = !sidebarOpen" aria-label="{{ __('القائمة') }}" title="{{ __('القائمة') }}">
                    <span class="material-symbols-outlined">menu</span>
                </button>

                {{-- Breadcrumb: group › page, resolved from the nav table above so the
                     header always names where you actually are. --}}
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
                {{-- Server-rendered so it needs no clock script and matches the app locale. --}}
                <span class="me-2 hidden font-body-sm text-body-sm text-on-surface-variant xl:inline">
                    {{ now()->translatedFormat('l، j F Y') }}
                </span>

                <a href="{{ route('admin.dashboard') }}"
                    @mouseenter="showTipBelow($el, @js(__('لوحة التحكم')))" @mouseleave="hideTip()"
                    @focus="showTipBelow($el, @js(__('لوحة التحكم')))" @blur="hideTip()"
                    class="hidden h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary sm:flex"
                    aria-label="{{ __('لوحة التحكم') }}">
                    <span class="material-symbols-outlined">space_dashboard</span>
                </a>

                {{-- The locale code alone carries the meaning; the translate glyph
                     beside it was pure duplication. --}}
                <a href="{{ route('locale.switch', $otherLocale) }}"
                    @mouseenter="showTipBelow($el, @js(__('تغيير اللغة')))" @mouseleave="hideTip()"
                    @focus="showTipBelow($el, @js(__('تغيير اللغة')))" @blur="hideTip()"
                    class="flex h-10 min-w-10 items-center justify-center rounded-full px-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                    aria-label="{{ __('تغيير اللغة') }}">
                    {{ $otherLocale === 'en' ? 'EN' : 'ع' }}
                </a>

                <button type="button" @click="dark = !dark"
                    @mouseenter="showTipBelow($el, dark ? @js(__('الوضع الفاتح')) : @js(__('الوضع الداكن')))" @mouseleave="hideTip()"
                    @focus="showTipBelow($el, dark ? @js(__('الوضع الفاتح')) : @js(__('الوضع الداكن')))" @blur="hideTip()"
                    class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                    :aria-label="dark ? '{{ __('الوضع الفاتح') }}' : '{{ __('الوضع الداكن') }}'">
                    <span class="material-symbols-outlined" x-text="dark ? 'light_mode' : 'dark_mode'"></span>
                </button>

                {{-- Account menu. Nested x-data so `open` is scoped to this widget
                     and cannot collide with the sidebar/search state on the root. --}}
                <div class="relative ms-1" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                    <button type="button" @click="open = !open"
                        @mouseenter="showTipBelow($el, @js(__('حساب المشرف')))" @mouseleave="hideTip()"
                        class="flex items-center gap-2 rounded-full p-1 transition-colors hover:bg-surface-container-high"
                        :aria-expanded="open.toString()" aria-haspopup="menu"
                        aria-label="{{ __('حساب المشرف') }}">
                        <span class="grid h-9 w-9 flex-shrink-0 place-items-center rounded-full bg-primary-container font-bold text-on-primary-container">
                            {{ $admin ? mb_substr($admin->name, 0, 1) : '?' }}
                        </span>
                        <span class="material-symbols-outlined !text-lg text-on-surface-variant transition-transform" :class="open && 'rotate-180'">expand_more</span>
                    </button>

                    <div x-show="open" x-cloak style="display:none"
                        class="absolute end-0 top-12 z-50 w-72 overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                        role="menu">
                        {{-- Identity header: avatar + name + role, on a tinted panel so it
                             reads as context rather than a fourth menu row. --}}
                        <div class="flex items-center gap-3 border-b border-outline-variant/60 bg-surface-container-low p-4">
                            <span class="grid h-11 w-11 flex-shrink-0 place-items-center rounded-full bg-primary-container font-display-lg text-lg font-bold text-on-primary-container">
                                {{ $admin ? mb_substr($admin->name, 0, 1) : '?' }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-body-sm text-body-sm font-bold text-on-surface" dir="auto">{{ $admin?->name }}</span>
                                <span class="block truncate font-body-sm text-[11px] text-on-surface-variant" dir="ltr">{{ $admin?->email }}</span>
                            </span>
                        </div>

                        {{-- Items are inset and individually rounded so the hover state is a
                             contained pill rather than a full-bleed band. The icon sits in its
                             own chip, and a chevron fades in on hover to signal navigation. --}}
                        <div class="p-2">
                            <a href="{{ route('admin.profile.index') }}" role="menuitem"
                                class="group flex items-center gap-3 rounded-xl px-3 py-2.5 font-body-sm text-body-sm font-bold text-on-surface-variant transition-colors hover:bg-primary/10 hover:text-primary">
                                <span class="grid h-9 w-9 flex-shrink-0 place-items-center rounded-lg bg-surface-container text-on-surface-variant transition-colors group-hover:bg-primary group-hover:text-on-primary">
                                    <span class="material-symbols-outlined !text-xl">person</span>
                                </span>
                                <span class="flex-1">{{ __('الملف الشخصي') }}</span>
                                <span class="material-symbols-outlined !text-lg opacity-0 transition-opacity group-hover:opacity-100 rtl:-scale-x-100">chevron_right</span>
                            </a>

                            <a href="{{ route('admin.settings.index') }}" role="menuitem"
                                class="group flex items-center gap-3 rounded-xl px-3 py-2.5 font-body-sm text-body-sm font-bold text-on-surface-variant transition-colors hover:bg-primary/10 hover:text-primary">
                                <span class="grid h-9 w-9 flex-shrink-0 place-items-center rounded-lg bg-surface-container text-on-surface-variant transition-colors group-hover:bg-primary group-hover:text-on-primary">
                                    <span class="material-symbols-outlined !text-xl">settings</span>
                                </span>
                                <span class="flex-1">{{ __('الإعدادات') }}</span>
                                <span class="material-symbols-outlined !text-lg opacity-0 transition-opacity group-hover:opacity-100 rtl:-scale-x-100">chevron_right</span>
                            </a>

                            <div class="my-1 border-t border-outline-variant/60"></div>

                            {{-- Reuses the layout-level confirm modal; the form itself lives
                                 in the sidebar footer, referenced here by id. --}}
                            <button type="button" role="menuitem"
                                @click="open = false; confirmOpen = true; confirmMessage = '{{ __('هل تريد تسجيل الخروج من حسابك؟') }}'; confirmFormId = 'admin-logout-form'; confirmTitle = '{{ __('تأكيد تسجيل الخروج') }}'; confirmLabel = '{{ __('تسجيل الخروج') }}'"
                                class="group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 font-body-sm text-body-sm font-bold text-error transition-colors hover:bg-error/10">
                                <span class="grid h-9 w-9 flex-shrink-0 place-items-center rounded-lg bg-error/10 text-error transition-colors group-hover:bg-error group-hover:text-on-error">
                                    <span class="material-symbols-outlined !text-xl rtl:-scale-x-100">logout</span>
                                </span>
                                <span class="flex-1 text-start">{{ __('تسجيل الخروج') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
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

    {{-- The one shared sidebar tooltip. Fixed, so no ancestor's overflow can
         clip it, and rendered LAST in the body so it also wins on DOM order —
         page cards sit inside `.page-enter`, whose fill-mode:both animation
         leaves a transform in place and therefore its own stacking context,
         which was painting over the tooltip when it lived higher up. --}}
    <div x-show="tipText !== ''" x-cloak
        class="pointer-events-none fixed z-[90] whitespace-nowrap rounded-lg bg-inverse-surface px-3 py-1.5 font-body-sm text-body-sm font-bold text-inverse-on-surface shadow-xl"
        {{-- The translate differs per placement, so it lives in the bound style
             rather than a utility class. `side` clears whichever sidebar width is
             active (5.5rem / 16rem); `below` centres on the header control using
             physical left, which is already direction-correct off getBoundingClientRect. --}}
        :style="tipPlacement === 'side'
            ? `top:${tipTop}px; inset-inline-start:${collapsed ? '6rem' : '17rem'}; transform:translateY(-50%);`
            : `top:${tipTop}px; left:${tipLeft}px; transform:translateX(-50%);`"
        x-text="tipText" role="tooltip" style="display:none"></div>
</body>
</html>
