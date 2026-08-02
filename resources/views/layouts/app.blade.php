@php
    $member = auth('tenant')->user();

    $catalogNaming = app(\App\Services\Tenant\CatalogNaming::class);
    $catalogLabel = $catalogNaming->catalog();
    $catalogIcon = $catalogNaming->icon();
    $catalogConfigured = $catalogNaming->isConfigured();

    $navGroups = [
        [
            'label' => 'عام',
            'items' => [
                ['href' => route('dashboard'), 'icon' => 'dashboard', 'label' => 'لوحة التحكم', 'match' => 'dashboard', 'permission' => null],
                ['href' => '/transfers', 'icon' => 'send', 'label' => 'طلب جديد', 'match' => 'transfers*', 'permission' => \App\Enums\PermissionKey::TransfersCreate],
                ['href' => '/scheduling', 'icon' => 'calendar_month', 'label' => 'المواعيد', 'match' => 'scheduling*', 'permission' => \App\Enums\PermissionKey::TransfersCreate, 'capability' => \App\Enums\BusinessCapability::Scheduling],
                ['href' => '/work-orders', 'icon' => 'build', 'label' => 'أوامر الشغل', 'match' => 'work-orders*', 'permission' => \App\Enums\PermissionKey::TransfersCreate, 'capability' => \App\Enums\BusinessCapability::Scheduling],
                ['href' => '/returns', 'icon' => 'assignment_return', 'label' => 'المرتجعات', 'match' => 'returns*', 'permission' => \App\Enums\PermissionKey::TransfersCreate],
                ['href' => '/history', 'icon' => 'history', 'label' => 'السجل', 'match' => 'history*', 'permission' => null],
                ['href' => '/customers', 'icon' => 'contacts', 'label' => 'العملاء', 'match' => 'customers*', 'permission' => null],
            ],
        ],
        [
            'label' => 'الإدارة',
            'items' => [
                ['href' => '/accounts', 'icon' => 'wallet', 'label' => 'الحسابات', 'match' => 'accounts*', 'permission' => \App\Enums\PermissionKey::BanksManage],
                ['href' => '/catalogs', 'icon' => 'category', 'label' => 'الكتالوجات', 'match' => 'catalogs*', 'permission' => \App\Enums\PermissionKey::BanksManage],

                ['href' => '/menu', 'icon' => $catalogIcon, 'label' => $catalogLabel, 'match' => 'menu*', 'permission' => \App\Enums\PermissionKey::MenuManage, 'feature' => \App\Enums\PackageFeature::Menu, 'translated' => true, 'visible' => $catalogConfigured],
                ['href' => '/recipes', 'icon' => 'blender', 'label' => 'الوصفات', 'match' => 'recipes*', 'permission' => \App\Enums\PermissionKey::MenuManage, 'capability' => \App\Enums\BusinessCapability::Recipes],
                ['href' => '/modifiers', 'icon' => 'tune', 'label' => 'الإضافات والخيارات', 'match' => 'modifiers*', 'permission' => \App\Enums\PermissionKey::MenuManage, 'capability' => \App\Enums\BusinessCapability::Modifiers],
                ['href' => '/allergens', 'icon' => 'allergies', 'label' => 'مسبّبات الحساسية', 'match' => 'allergens*', 'permission' => \App\Enums\PermissionKey::MenuManage, 'capability' => \App\Enums\BusinessCapability::Allergens],
                ['href' => '/variants', 'icon' => 'grid_view', 'label' => 'التنويعات', 'match' => 'variants*', 'permission' => \App\Enums\PermissionKey::MenuManage, 'capability' => \App\Enums\BusinessCapability::Variants],
                ['href' => '/fitment', 'icon' => 'directions_car', 'label' => 'بحث القطع', 'match' => 'fitment*', 'permission' => null, 'capability' => \App\Enums\BusinessCapability::Fitment],
                ['href' => '/pharmacy', 'icon' => 'prescriptions', 'label' => 'سجل الصرف', 'match' => 'pharmacy*', 'permission' => null, 'capability' => \App\Enums\BusinessCapability::Prescription],
                ['href' => '/fuel', 'icon' => 'local_gas_station', 'label' => 'الورديات والخزانات', 'match' => 'fuel*', 'permission' => \App\Enums\PermissionKey::InventoryManage, 'capability' => \App\Enums\BusinessCapability::FuelStation],
                ['href' => '/production', 'icon' => 'factory', 'label' => 'الإنتاج', 'match' => 'production*', 'permission' => \App\Enums\PermissionKey::InventoryManage, 'capability' => \App\Enums\BusinessCapability::Recipes],
                ['href' => '/inventory', 'icon' => 'inventory_2', 'label' => 'المخزون', 'match' => 'inventory*', 'permission' => \App\Enums\PermissionKey::InventoryManage, 'capability' => \App\Enums\BusinessCapability::Inventory],
                ['href' => '/batches', 'icon' => 'event_available', 'label' => 'اللوط والصلاحية', 'match' => 'batches*', 'permission' => \App\Enums\PermissionKey::InventoryManage, 'capability' => \App\Enums\BusinessCapability::Batches],
                ['href' => '/serials', 'icon' => 'pin', 'label' => 'الأرقام التسلسلية', 'match' => 'serials*', 'permission' => \App\Enums\PermissionKey::InventoryManage, 'capability' => \App\Enums\BusinessCapability::Serials],
                ['href' => '/warranty', 'icon' => 'verified_user', 'label' => 'الضمان والصيانة', 'match' => 'warranty*', 'permission' => \App\Enums\PermissionKey::InventoryManage, 'capability' => \App\Enums\BusinessCapability::Warranty],
                ['href' => '/stock-counts', 'icon' => 'checklist', 'label' => 'الجرد', 'match' => 'stock-counts*', 'permission' => \App\Enums\PermissionKey::InventoryManage, 'capability' => \App\Enums\BusinessCapability::Inventory],
                ['href' => '/purchases', 'icon' => 'local_shipping', 'label' => 'المشتريات', 'match' => 'purchases*', 'permission' => \App\Enums\PermissionKey::PurchasesManage, 'capability' => \App\Enums\BusinessCapability::Purchasing],
                ['href' => '/suppliers', 'icon' => 'store', 'label' => 'الموردون', 'match' => 'suppliers*', 'permission' => \App\Enums\PermissionKey::PurchasesManage, 'capability' => \App\Enums\BusinessCapability::Purchasing],
                ['href' => '/expenses', 'icon' => 'payments', 'label' => 'المصروفات', 'match' => 'expenses*', 'permission' => \App\Enums\PermissionKey::ExpensesManage, 'feature' => \App\Enums\PackageFeature::Expenses],
                ['href' => '/balance', 'icon' => 'balance', 'label' => 'الموازنة المالية', 'match' => 'balance*', 'permission' => null],
                ['href' => '/reports', 'icon' => 'assessment', 'label' => 'التقارير', 'match' => 'reports*', 'permission' => null],
            ],
        ],
        [
            'label' => 'النظام',
            'items' => [
                ['href' => '/constants', 'icon' => 'tune', 'label' => 'الثوابت', 'match' => 'constants*', 'permission' => \App\Enums\PermissionKey::ConstantsManage],
                ['href' => '/team', 'icon' => 'group', 'label' => 'الفريق', 'match' => 'team*', 'permission' => \App\Enums\PermissionKey::TeamManage, 'feature' => \App\Enums\PackageFeature::Team],
                ['href' => '/roles', 'icon' => 'admin_panel_settings', 'label' => 'الأدوار والصلاحيات', 'match' => 'roles*', 'permission' => \App\Enums\PermissionKey::TeamManage, 'feature' => \App\Enums\PackageFeature::Team],
                ['href' => '/devices', 'icon' => 'devices', 'label' => 'الأجهزة', 'match' => 'devices*', 'permission' => \App\Enums\PermissionKey::DevicesManage, 'feature' => \App\Enums\PackageFeature::Devices],
                ['href' => '/activity-log', 'icon' => 'fact_check', 'label' => 'سجل النشاطات', 'match' => 'activity-log*', 'permission' => null],
                ['href' => '/settings', 'icon' => 'settings', 'label' => 'الإعدادات', 'match' => 'settings', 'permission' => null],
                ['href' => '/settings/whatsapp', 'icon' => 'chat', 'label' => 'واتساب', 'match' => 'settings/whatsapp*', 'permission' => \App\Enums\PermissionKey::ConstantsManage, 'feature' => \App\Enums\PackageFeature::WhatsApp],
                ['href' => '/help', 'icon' => 'help', 'label' => 'المساعدة', 'match' => 'help*', 'permission' => null],
            ],
        ],
    ];

    $currentLicense = app()->bound('currentLicense') ? app('currentLicense') : null;
    $business = app(\App\Services\Tenant\BusinessProfile::class);

    /*
     * Three independent gates, and they answer different questions:
     * `permission` is who the member is, `feature` is what the operator sold, and
     * `capability` is what the trade needs. A pharmacy without batch tracking is
     * not a downgrade to sell around - it is the wrong system.
     */
    $visibleGroups = [];
    foreach ($navGroups as $group) {
        $items = array_values(array_filter(
            $group['items'],

            fn ($item) => ($item['visible'] ?? true)
                && (! ($item['permission'] ?? null) || $member?->hasPermission($item['permission']))
                && (! ($item['feature'] ?? null) || (bool) $currentLicense?->hasFeature($item['feature']))
                && (! ($item['capability'] ?? null) || $business->hasAny((array) $item['capability'])),
        ));

        if ($items !== []) {
            $visibleGroups[] = ['label' => $group['label'], 'items' => $items];
        }
    }

    $currentSection = null;
    $currentGroup = null;
    foreach ($visibleGroups as $group) {
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
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="lang-{{ app()->getLocale() }}"     x-data="{
        dark: localStorage.getItem('theme') === 'dark',
        sidebarOpen: false,
        collapsed: localStorage.getItem('tenantSidebarCollapsed') === '1',
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
        $watch('collapsed', v => { localStorage.setItem('tenantSidebarCollapsed', v ? '1' : '0'); tipText = '' });
    "
    :class="{ dark: dark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('حوالاتي'))</title>

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#00236f">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

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
            <a href="{{ route('dashboard') }}"
                @mouseenter="showTip($el, @js(__('حوالاتي')))" @mouseleave="hideTip()"
                @focus="showTip($el, @js(__('حوالاتي')))" @blur="hideTip()"
                class="flex-shrink-0 rounded-xl transition-transform duration-200 hover:scale-105 active:scale-95"
                aria-label="{{ __('حوالاتي') }}" title="{{ __('حوالاتي') }}">
                <x-logo />
            </a>
            <div class="min-w-0 flex-1" :class="collapsed && 'lg:hidden'">
                <span class="block font-display-lg text-2xl font-bold leading-tight text-on-sidebar">{{ __('حوالاتي') }}</span>
                <p class="font-body-sm text-body-sm text-on-sidebar-muted">{{ __('تحويلاتي') }}</p>
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
            @foreach ($visibleGroups as $group)
                <p class="px-4 pb-1 pt-4 font-label-caps text-label-caps uppercase tracking-wider text-on-sidebar-muted"
                    :class="collapsed && 'lg:hidden'">{{ __($group['label']) }}</p>
                <div class="mx-3 hidden border-t border-sidebar-border" :class="collapsed ? 'lg:mt-3 lg:block' : 'lg:hidden'"></div>

                @foreach ($group['items'] as $item)
                    @php
                        $isActive = request()->is($item['match']);

                        $label = ($item['translated'] ?? false) ? $item['label'] : __($item['label']);
                    @endphp
                    <a
                        href="{{ $item['href'] }}"
                        @if ($isActive) aria-current="page" @endif
                        @mouseenter="showTip($el, @js($label))" @mouseleave="hideTip()"
                        @focus="showTip($el, @js($label))" @blur="hideTip()"
                        class="flex items-center gap-3 rounded-lg border-s-4 px-4 py-3 font-body-lg text-body-lg transition-all duration-200 {{ $isActive ? 'border-sidebar-accent bg-sidebar-active font-bold text-white' : 'border-transparent text-on-sidebar-muted hover:bg-sidebar-hover hover:text-on-sidebar' }}"
                        :class="collapsed && 'lg:justify-center lg:gap-0 lg:px-0'">
                        <span class="material-symbols-outlined flex-shrink-0 {{ $item['icon'] === 'send' ? 'rtl:-scale-x-100' : '' }}" @if ($isActive) data-weight="fill" @endif>{{ $item['icon'] }}</span>
                        <span class="truncate" :class="collapsed && 'lg:hidden'">{{ $label }}</span>
                    </a>
                @endforeach
            @endforeach
        </nav>

        <div class="mt-auto border-t border-sidebar-border px-inline-md pt-4" :class="collapsed && 'lg:px-2'">
            @if ($member?->hasPermission(\App\Enums\PermissionKey::TransfersCreate))
                <a
                    href="/transfers"
                    @mouseenter="showTip($el, @js(__('طلب جديد')))" @mouseleave="hideTip()"
                    class="mb-4 flex w-full items-center justify-center gap-2 rounded-xl bg-secondary-container px-4 py-3 font-bold text-on-secondary-fixed shadow-lg transition-transform active:scale-95"
                    :class="collapsed && 'lg:px-0'"
                    aria-label="{{ __('طلب جديد') }}" title="{{ __('طلب جديد') }}">
                    <span class="material-symbols-outlined flex-shrink-0">add</span>
                    <span :class="collapsed && 'lg:hidden'">{{ __('طلب جديد') }}</span>
                </a>
            @endif

            <a href="/settings"
                @mouseenter="showTip($el, @js($member?->name ?? ''))" @mouseleave="hideTip()"
                class="flex items-center gap-3 rounded-xl p-2 transition-colors hover:bg-sidebar-hover"
                :class="collapsed && 'lg:justify-center lg:p-1'"
                title="{{ __('الإعدادات') }}">
                <span class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-full bg-sidebar-accent/15 font-bold text-sidebar-accent ring-1 ring-sidebar-accent/30">
                    {{ $member ? mb_substr($member->name, 0, 1) : '?' }}
                </span>
                <span class="min-w-0 flex-1" :class="collapsed && 'lg:hidden'">
                    <span class="block truncate text-start font-body-sm text-body-sm font-bold text-on-sidebar"><span dir="auto">{{ $member?->name }}</span></span>
                    <span class="block truncate text-start font-body-sm text-[11px] text-on-sidebar-muted"><span dir="ltr">{{ $member?->email }}</span></span>
                </span>
            </a>

            <form method="POST" action="{{ route('logout') }}" id="tenant-logout-form"
                @submit="$store.offline.wipe()">@csrf</form>
            <button
                type="button"
                @click="confirmOpen = true; confirmMessage = '{{ __('هل تريد تسجيل الخروج من حسابك؟') }}'; confirmFormId = 'tenant-logout-form'; confirmTitle = '{{ __('تأكيد تسجيل الخروج') }}'; confirmLabel = '{{ __('تسجيل الخروج') }}'"
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
            <div class="flex flex-1 items-center gap-3">
                <button type="button" class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary lg:hidden" @click="hideTip(); sidebarOpen = !sidebarOpen"
                    @mouseenter="showTipBelow($el, @js(__('القائمة')))" @mouseleave="hideTip()"
                    @focus="showTipBelow($el, @js(__('القائمة')))" @blur="hideTip()"
                    aria-label="{{ __('القائمة') }}" title="{{ __('القائمة') }}">
                    <span class="material-symbols-outlined">menu</span>
                </button>

                @if ($currentSection)
                    <nav class="hidden min-w-0 items-center gap-2 md:flex" aria-label="{{ __('مسار التنقل') }}">
                        <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <span class="material-symbols-outlined !text-xl {{ $currentSection['icon'] === 'send' ? 'rtl:-scale-x-100' : '' }}">{{ $currentSection['icon'] }}</span>
                        </span>
                        <span class="min-w-0">
                            <span class="block font-label-caps text-label-caps uppercase tracking-wider text-outline">{{ __($currentGroup) }}</span>
                            <span class="block truncate font-headline-md text-body-lg font-bold text-on-surface">{{ __($currentSection['label']) }}</span>
                        </span>
                    </nav>
                @endif
                <div class="relative sm:w-full sm:max-w-lg" x-data="globalSearch()" @click.outside="open = false">
                    <div class="hidden w-full items-center gap-2 rounded-full bg-surface-container px-4 py-2 ring-1 ring-transparent transition-all focus-within:bg-surface-container-lowest focus-within:ring-primary/40 sm:flex">
                        <span class="material-symbols-outlined flex-shrink-0 !text-xl text-outline">search</span>
                        <input type="text" x-model="query" x-ref="desktopSearchInput" autocomplete="off"
                            role="combobox" aria-autocomplete="list" aria-controls="search-results-desktop"
                            :aria-expanded="open ? 'true' : 'false'"
                            @input="onInput()"
                            @focus="open = hasResults || showRecent"
                            @keydown.arrow-down.prevent="move(1)"
                            @keydown.arrow-up.prevent="move(-1)"
                            @keydown.enter.prevent="choose()"
                            @keydown.escape.prevent="open = false; $el.blur()"
                            aria-label="{{ __('بحث عن معاملة أو مستلم') }}" placeholder="{{ __('بحث عن معاملة أو مستلم...') }}"
                            class="w-full border-none bg-transparent font-body-sm text-sm outline-none placeholder:text-outline focus:ring-0" />
                        <span x-show="loading" x-cloak class="material-symbols-outlined flex-shrink-0 animate-spin !text-lg text-outline">progress_activity</span>
                        <button type="button" x-show="query.length" x-cloak @click="hideTip(); reset(); $refs.desktopSearchInput.focus()"
                            @mouseenter="showTipBelow($el, @js(__('مسح')))" @mouseleave="hideTip()"
                            @focus="showTipBelow($el, @js(__('مسح')))" @blur="hideTip()"
                            aria-label="{{ __('مسح') }}" title="{{ __('مسح') }}"
                            class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full text-outline transition-colors hover:bg-surface-container-high hover:text-on-surface">
                            <span class="material-symbols-outlined !text-base">close</span>
                        </button>
                        <kbd x-show="!query.length && !loading"
                            class="hidden flex-shrink-0 rounded-md border border-outline-variant px-1.5 py-0.5 font-numeral-lg text-[10px] font-bold text-outline lg:block" dir="ltr">Ctrl K</kbd>
                    </div>

                    <div id="search-results-desktop" role="listbox" x-show="open && showPanel" x-cloak
                        x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                        class="absolute inset-x-0 top-13 z-50 hidden max-h-96 overflow-y-auto rounded-2xl border border-outline-variant bg-surface-container-lowest p-2 shadow-2xl sm:block">
                        <x-search-results-list />
                    </div>

                    <button type="button" @click="focusSearch()"
                        class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary sm:hidden"
                        aria-label="{{ __('بحث') }}">
                        <span class="material-symbols-outlined">search</span>
                    </button>

                    <div x-show="mobileOpen" x-cloak @keydown.escape.window="closeMobile()"
                        class="fixed inset-0 z-[100] flex flex-col bg-surface sm:hidden" style="display:none">
                        <div class="flex items-center gap-2 border-b border-outline-variant p-4">
                            <span class="material-symbols-outlined flex-shrink-0 text-outline">search</span>
                            <input type="text" x-model="query" x-ref="mobileSearchInput" autocomplete="off"
                                role="combobox" aria-autocomplete="list" aria-controls="search-results-mobile"
                                :aria-expanded="showPanel ? 'true' : 'false'"
                                @input="onInput()"
                                @keydown.arrow-down.prevent="move(1)"
                                @keydown.arrow-up.prevent="move(-1)"
                                @keydown.enter.prevent="choose()"
                                aria-label="{{ __('بحث عن معاملة أو مستلم') }}" placeholder="{{ __('بحث عن معاملة أو مستلم...') }}"
                                class="w-full border-none bg-transparent font-body-lg text-body-lg outline-none placeholder:text-outline focus:ring-0" />
                            <span x-show="loading" x-cloak class="material-symbols-outlined flex-shrink-0 animate-spin !text-lg text-outline">progress_activity</span>
                            <button type="button" @click="closeMobile()" aria-label="{{ __('إغلاق') }}" title="{{ __('إغلاق') }}"
                                class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high">
                                <span class="material-symbols-outlined">close</span>
                            </button>
                        </div>
                        <div id="search-results-mobile" role="listbox" class="flex-1 overflow-y-auto p-2">
                            <x-search-results-list />
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4 md:gap-6">
                <div x-show="!$store.offline.online || $store.offline.pending" x-cloak
                    class="flex items-center gap-2 rounded-full px-3 py-1.5 font-body-sm font-bold"
                    :class="$store.offline.online ? 'bg-tertiary/15 text-tertiary' : 'bg-error/15 text-error'"
                    :title="$store.offline.online
                        ? @js(__('جارٍ إرسال الطلبات المحفوظة...'))
                        : @js(__('لا يوجد اتصال - الطلبات تُحفظ على هذا الجهاز'))">
                    <span class="material-symbols-outlined !text-xl"
                        x-text="$store.offline.online ? 'sync' : 'cloud_off'"
                        :class="$store.offline.online && $store.offline.pending ? 'animate-spin' : ''"></span>
                    <span x-show="$store.offline.pending" class="font-numeral-lg" x-text="$store.offline.pending"></span>
                    <span class="hidden sm:inline" x-show="!$store.offline.online">{{ __('بدون اتصال') }}</span>
                </div>

                <div class="relative" x-data="notificationBell({{ auth('tenant')->user()->unreadNotifications()->count() }})" @click.outside="open = false">
                    <button type="button" @click="toggle()"
                        @mouseenter="showTipBelow($el, @js(__('الإشعارات')))" @mouseleave="hideTip()"
                        @focus="showTipBelow($el, @js(__('الإشعارات')))" @blur="hideTip()"
                        class="relative flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('الإشعارات') }}" title="{{ __('الإشعارات') }}">
                        <span class="material-symbols-outlined">notifications</span>
                        <span x-show="unreadCount > 0" x-cloak x-text="unreadCount > 9 ? '9+' : unreadCount"
                            class="absolute -end-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-error px-1 font-numeral-lg text-[10px] font-bold text-on-error"></span>
                    </button>

                    <div x-show="open" x-cloak @keydown.escape.window="open = false"
                        x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                        class="absolute end-0 top-12 z-50 w-[calc(100vw-2rem)] max-w-80 overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest shadow-2xl sm:w-96 sm:max-w-none">
                        <div class="flex items-center justify-between border-b border-outline-variant/60 p-4">
                            <h3 class="font-headline-md text-headline-md text-on-surface">{{ __('الإشعارات') }}</h3>
                            <button type="button" @click="markAllRead()" x-show="unreadCount > 0" class="font-body-sm font-bold text-primary hover:underline" title="{{ __('تعليم الكل كمقروء') }}">{{ __('تعليم الكل كمقروء') }}</button>
                        </div>

                        <div class="max-h-96 overflow-y-auto">
                            <template x-if="loading">
                                <p class="p-6 text-center font-body-sm text-outline">{{ __('جارٍ التحميل...') }}</p>
                            </template>
                            <template x-if="!loading && notifications.length === 0">
                                <div class="flex flex-col items-center gap-2 py-10 text-center">
                                    <span class="material-symbols-outlined !text-4xl text-outline/50">notifications_off</span>
                                    <p class="font-body-sm text-outline">{{ __('لا توجد إشعارات') }}</p>
                                </div>
                            </template>
                            <template x-for="n in notifications" :key="n.id">
                                <button type="button" @click="markRead(n)" class="flex w-full items-start gap-3 border-b border-outline-variant/40 p-4 text-start transition-colors hover:bg-surface-container-low"
                                    :class="!n.readAt && 'bg-primary/5'">
                                    <span class="mt-0.5 flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full"
                                        :class="n.data.kind === 'new_order' ? 'bg-primary/10 text-primary' : (n.data.status === 'completed' ? 'bg-success-container text-success dark:bg-success-container/15' : 'bg-error-container text-error dark:bg-error/15')">
                                        <span class="material-symbols-outlined !text-xl" x-text="n.data.kind === 'new_order' ? 'receipt_long' : (n.data.status === 'completed' ? 'check_circle' : 'error')"></span>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-body-sm font-bold text-on-surface" x-text="n.data.kind === 'new_order' ? '{{ __('طلب جديد من') }} ' + n.data.customer : '{{ __('تغيّرت حالة طلب') }} ' + n.data.customer"></p>
                                        <p class="mt-0.5 text-[11px] text-on-surface-variant" x-text="n.createdAt"></p>
                                    </div>
                                    <span x-show="!n.readAt" class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full bg-primary"></span>
                                </button>
                            </template>
                        </div>
                        <a href="{{ route('notifications.index') }}" @click="open = false"
                            class="block border-t border-outline-variant/60 p-3 text-center font-body-sm font-bold text-primary transition-colors hover:bg-surface-container-low">
                            {{ __('عرض كل الإشعارات') }}
                        </a>
                    </div>
                </div>

                <div class="flex items-center gap-1 border-s border-outline-variant ps-3 md:ps-4">
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
                            @mouseenter="showTipBelow($el, @js(__('حسابي')))" @mouseleave="hideTip()"
                            @focus="showTipBelow($el, @js(__('حسابي')))" @blur="hideTip()"
                            class="flex items-center gap-2 rounded-full p-1 transition-colors hover:bg-surface-container-high"
                            :aria-expanded="open.toString()" aria-haspopup="menu" aria-label="{{ __('حسابي') }}" title="{{ __('حسابي') }}">
                            <span class="grid h-9 w-9 flex-shrink-0 place-items-center rounded-full bg-primary-container font-bold text-on-primary-container">
                                {{ $member ? mb_substr($member->name, 0, 1) : '?' }}
                            </span>
                            <span class="material-symbols-outlined !text-lg text-on-surface-variant transition-transform" :class="open && 'rotate-180'">expand_more</span>
                        </button>

                        <div x-show="open" x-cloak style="display:none"
                            class="absolute end-0 top-12 z-50 w-72 overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                            role="menu">
                            <div class="flex items-center gap-3 border-b border-outline-variant/60 bg-surface-container-low p-3">
                                <span class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-full bg-primary-container font-body-lg text-base font-bold text-on-primary-container">
                                    {{ $member ? mb_substr($member->name, 0, 1) : '?' }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-start font-body-sm text-body-sm font-bold text-on-surface"><span dir="auto">{{ $member?->name }}</span></span>
                                    <span class="block truncate text-start font-body-sm text-[11px] text-on-surface-variant"><span dir="ltr">{{ $member?->email }}</span></span>
                                </span>
                            </div>

                            <div class="p-1.5">
                                <a href="/settings" role="menuitem"
                                    class="group flex items-center gap-3 rounded-xl px-2.5 py-2 font-body-sm text-body-sm font-bold text-on-surface-variant transition-colors hover:bg-primary/10 hover:text-primary">
                                    <span class="grid h-8 w-8 flex-shrink-0 place-items-center rounded-lg bg-surface-container text-on-surface-variant transition-colors group-hover:bg-primary group-hover:text-on-primary">
                                        <span class="material-symbols-outlined !text-xl">person</span>
                                    </span>
                                    <span class="flex-1">{{ __('الإعدادات') }}</span>
                                    <span class="material-symbols-outlined !text-lg opacity-0 transition-opacity group-hover:opacity-100 rtl:-scale-x-100">chevron_right</span>
                                </a>

                                <a href="/help" role="menuitem"
                                    class="group flex items-center gap-3 rounded-xl px-2.5 py-2 font-body-sm text-body-sm font-bold text-on-surface-variant transition-colors hover:bg-primary/10 hover:text-primary">
                                    <span class="grid h-8 w-8 flex-shrink-0 place-items-center rounded-lg bg-surface-container text-on-surface-variant transition-colors group-hover:bg-primary group-hover:text-on-primary">
                                        <span class="material-symbols-outlined !text-xl">help</span>
                                    </span>
                                    <span class="flex-1">{{ __('المساعدة') }}</span>
                                    <span class="material-symbols-outlined !text-lg opacity-0 transition-opacity group-hover:opacity-100 rtl:-scale-x-100">chevron_right</span>
                                </a>

                                <div class="my-1 border-t border-outline-variant/60"></div>

                                <button type="button" role="menuitem"
                                    @click="open = false; confirmOpen = true; confirmMessage = '{{ __('هل تريد تسجيل الخروج من حسابك؟') }}'; confirmFormId = 'tenant-logout-form'; confirmTitle = '{{ __('تأكيد تسجيل الخروج') }}'; confirmLabel = '{{ __('تسجيل الخروج') }}'"
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
