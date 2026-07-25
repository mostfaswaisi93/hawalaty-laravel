@php $otherLocale = app()->getLocale() === 'ar' ? 'en' : 'ar'; @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="lang-{{ app()->getLocale() }}"
    x-data="{ dark: localStorage.getItem('theme') === 'dark' }"
    x-init="$watch('dark', v => { document.documentElement.classList.toggle('dark', v); localStorage.setItem('theme', v ? 'dark' : 'light') }); document.documentElement.classList.toggle('dark', dark)"
    :class="{ dark: dark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('حوالاتي') }} - {{ __('منصّة إدارة الطلبات والحوالات للمطاعم والأعمال') }}</title>
    <meta name="description" content="{{ __('منصّة عربية متكاملة لإدارة الطلبات، البنوك والمحافظ، القوائم، المصروفات والتقارير - بسرعة وأمان.') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&family=Cairo:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        @keyframes floaty { 0%, 100% { transform: translateY(0) } 50% { transform: translateY(-14px) } }
        .floaty { animation: floaty 6s ease-in-out infinite }
        .reveal { opacity: 0; transform: translateY(26px); transition: opacity .7s cubic-bezier(.16, 1, .3, 1), transform .7s cubic-bezier(.16, 1, .3, 1) }
        .reveal.is-visible { opacity: 1; transform: none }
        .grid-dots { background-image: radial-gradient(rgb(var(--c-outline-variant) / .55) 1px, transparent 1px); background-size: 30px 30px }
        @media (prefers-reduced-motion: reduce) {
            .reveal { opacity: 1; transform: none; transition: none }
            .floaty { animation: none }
        }
    </style>
</head>
<body class="min-h-screen bg-surface font-sans text-on-surface antialiased">

    {{-- ─────────────────────────  NAV  ───────────────────────── --}}
    <header x-data="{ open: false }" class="sticky top-0 z-50 border-b border-outline-variant/60 bg-surface/80 backdrop-blur-lg">
        <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between px-inline-md md:h-20 md:px-inline-lg">
            <a href="{{ url('/') }}" class="flex items-center gap-3" title="{{ __('حوالاتي') }}">
                <x-logo />
                <span class="font-display-lg text-2xl font-bold leading-none text-primary">{{ __('حوالاتي') }}</span>
            </a>

            <div class="hidden items-center gap-10 md:flex">
                <a href="#features" class="font-body-sm text-on-surface-variant transition-colors hover:text-primary" title="{{ __('المزايا') }}">{{ __('المزايا') }}</a>
                <a href="#how" class="font-body-sm text-on-surface-variant transition-colors hover:text-primary" title="{{ __('كيف يعمل') }}">{{ __('كيف يعمل') }}</a>
                <a href="#pricing" class="font-body-sm text-on-surface-variant transition-colors hover:text-primary" title="{{ __('الأسعار') }}">{{ __('الأسعار') }}</a>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('locale.switch', $otherLocale) }}"
                    class="flex h-10 items-center gap-1.5 rounded-full px-3 text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                    aria-label="{{ __('تغيير اللغة') }}" title="{{ __('تغيير اللغة') }}">
                    <span class="material-symbols-outlined">translate</span>
                    <span class="font-body-sm font-bold">{{ $otherLocale === 'en' ? 'EN' : 'ع' }}</span>
                </a>
                <button type="button" @click="dark = !dark"
                    class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                    aria-label="{{ __('تبديل المظهر') }}" title="{{ __('تبديل المظهر') }}">
                    <span class="material-symbols-outlined" x-text="dark ? 'light_mode' : 'dark_mode'"></span>
                </button>
                <a href="{{ route('login') }}" class="hidden rounded-xl px-4 py-2 font-body-sm font-bold text-on-surface-variant transition-colors hover:text-primary sm:inline-block" title="{{ __('تسجيل الدخول') }}">
                    {{ __('تسجيل الدخول') }}
                </a>
                <a href="{{ route('signup') }}" class="hidden rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg shadow-primary/20 transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 sm:inline-block" title="{{ __('ابدأ مجاناً') }}">
                    {{ __('ابدأ مجاناً') }}
                </a>
                <button type="button" @click="open = !open"
                    class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high md:hidden"
                    aria-label="{{ __('القائمة') }}" title="{{ __('القائمة') }}">
                    <span class="material-symbols-outlined" x-text="open ? 'close' : 'menu'"></span>
                </button>
            </div>
        </nav>

        {{-- mobile menu --}}
        <div x-show="open" x-cloak class="border-t border-outline-variant/60 bg-surface px-inline-md py-4 md:hidden" style="display:none">
            <div class="flex flex-col gap-1">
                <a href="#features" @click="open = false" class="rounded-lg px-3 py-2.5 font-body-lg text-on-surface-variant hover:bg-surface-container" title="{{ __('المزايا') }}">{{ __('المزايا') }}</a>
                <a href="#how" @click="open = false" class="rounded-lg px-3 py-2.5 font-body-lg text-on-surface-variant hover:bg-surface-container" title="{{ __('كيف يعمل') }}">{{ __('كيف يعمل') }}</a>
                <a href="#pricing" @click="open = false" class="rounded-lg px-3 py-2.5 font-body-lg text-on-surface-variant hover:bg-surface-container" title="{{ __('الأسعار') }}">{{ __('الأسعار') }}</a>
                <hr class="my-2 border-outline-variant/60">
                <a href="{{ route('login') }}" class="rounded-lg px-3 py-2.5 font-body-lg font-bold text-primary hover:bg-surface-container" title="{{ __('تسجيل الدخول') }}">{{ __('تسجيل الدخول') }}</a>
                <a href="{{ route('signup') }}" class="mt-1 rounded-xl bg-primary px-3 py-2.5 text-center font-bold text-on-primary" title="{{ __('ابدأ مجاناً') }}">{{ __('ابدأ مجاناً') }}</a>
            </div>
        </div>
    </header>

    <main>
        {{-- ─────────────────────────  HERO  ───────────────────────── --}}
        <section class="relative overflow-hidden">
            <div class="pointer-events-none absolute inset-0 grid-dots opacity-60"></div>
            <div class="pointer-events-none absolute -top-40 start-[-10%] h-[28rem] w-[28rem] rounded-full bg-primary/20 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-52 end-[-8%] h-[26rem] w-[26rem] rounded-full bg-secondary-container/25 blur-3xl"></div>

            <div class="relative mx-auto grid max-w-7xl items-center gap-16 px-inline-md py-24 md:px-inline-lg md:py-32 lg:grid-cols-[1.1fr_0.9fr] lg:gap-20 lg:py-36">
                <div class="page-enter text-center lg:text-start">
                    <span class="inline-flex items-center gap-2 rounded-full border border-outline-variant bg-surface-container-low px-4 py-1.5 font-body-sm text-on-surface-variant">
                        <span class="h-2 w-2 rounded-full bg-secondary-container"></span>
                        {{ __('منصّة عربية ثنائية اللغة للأعمال') }}
                    </span>

                    <h1 class="mt-8 font-display-lg text-4xl font-bold leading-[1.1] tracking-tight text-on-surface sm:text-5xl md:text-6xl lg:text-[64px]">
                        {{ __('أدر مطعمك وحوالاتك') }}
                        <span class="bg-gradient-to-l from-primary to-secondary bg-clip-text text-transparent">{{ __('من مكان واحد') }}</span>
                    </h1>

                    <p class="mx-auto mt-7 max-w-2xl font-body-lg leading-relaxed text-on-surface-variant lg:mx-0">
                        {{ __('منصّة متكاملة لإدارة الطلبات، البنوك والمحافظ، القوائم، المصروفات والتقارير - بواجهة أنيقة، صلاحيات دقيقة، وتقارير فورية تنمّي عملك.') }}
                    </p>

                    <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row lg:justify-start">
                        <a href="{{ route('signup') }}"
                            class="group flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-8 py-4 font-headline-md text-headline-md text-on-primary shadow-xl shadow-primary/25 transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-[0.98] sm:w-auto" title="{{ __('أنشئ حسابك المجاني') }}">
                            <span>{{ __('أنشئ حسابك المجاني') }}</span>
                            <span class="material-symbols-outlined rtl:-scale-x-100 transition-transform group-hover:translate-x-1 rtl:group-hover:-translate-x-1">arrow_forward</span>
                        </a>
                        <a href="{{ route('login') }}"
                            class="flex w-full items-center justify-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-8 py-4 font-headline-md text-headline-md text-on-surface transition-all hover:bg-surface-container active:scale-[0.98] sm:w-auto" title="{{ __('تسجيل الدخول') }}">
                            <span class="material-symbols-outlined rtl:-scale-x-100 text-primary">login</span>
                            <span>{{ __('تسجيل الدخول') }}</span>
                        </a>
                    </div>

                    <p class="mt-6 font-body-sm text-outline">{{ __('تجربة مجانية ١٤ يوماً - بدون بطاقة ائتمان.') }}</p>
                </div>

                {{-- floating dashboard preview --}}
                <div class="reveal is-visible relative mx-auto w-full max-w-lg lg:mx-0 lg:max-w-none" aria-hidden="true">
                    <div class="pointer-events-none absolute -inset-4 -z-10 hidden rounded-[2.5rem] bg-gradient-to-br from-primary-container/30 to-secondary-container/20 blur-2xl lg:block"></div>
                    <div class="floaty glass-card w-full rounded-3xl p-7 sm:p-8">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="font-body-sm text-on-surface-variant">{{ __('إجمالي الإيرادات') }}</p>
                                <p class="mt-1 font-display-lg text-4xl font-bold text-on-surface" dir="ltr">18,240.00 ₪</p>
                            </div>
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                <span class="material-symbols-outlined !text-2xl">point_of_sale</span>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center gap-2">
                            <span class="material-symbols-outlined text-green-600 dark:text-green-400">trending_up</span>
                            <span class="font-body-sm font-bold text-green-600 dark:text-green-400">+18%</span>
                            <span class="font-body-sm text-outline">{{ __('مقارنة بالأسبوع الماضي') }}</span>
                        </div>

                        <div class="mt-5 flex h-28 items-end gap-2">
                            @foreach ([40, 65, 35, 90, 55, 75, 60] as $i => $h)
                                <div class="grow-bar w-full rounded-t-lg {{ $h === 90 ? 'bg-secondary-container' : 'bg-primary-container opacity-40' }}" style="height: {{ $h }}%; --d: {{ 160 + $i * 60 }}ms"></div>
                            @endforeach
                        </div>

                        <div class="mt-6 space-y-3">
                            {{-- Mock customer names in this demo widget stay as-is across locales - proper names aren't translated (same convention as real order data elsewhere in the app). --}}
                            @foreach ([['أحمد أبو ياسين', __('مكتملة'), '+85.00', 'bg-green-500'], ['سميرة خليل', __('قيد الانتظار'), '+42.00', 'bg-secondary-container']] as [$name, $status, $amount, $dot])
                                <div class="flex items-center gap-3 rounded-2xl border border-outline-variant/60 bg-surface-container-lowest p-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-primary">
                                        <span class="material-symbols-outlined !text-xl">account_balance</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-body-sm font-bold">{{ $name }}</p>
                                        <p class="flex items-center gap-1.5 text-[11px] text-on-surface-variant">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>{{ $status }}
                                        </p>
                                    </div>
                                    <p class="font-bold text-primary" dir="ltr">{{ $amount }} ₪</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ─────────────────────────  STATS STRIP  ───────────────────────── --}}
        <section class="border-y border-outline-variant/50 bg-surface-container-low">
            <div class="mx-auto grid max-w-7xl grid-cols-2 gap-4 px-inline-md py-14 md:grid-cols-4 md:gap-6 md:px-inline-lg md:py-16">
                @foreach ([
                    ['bolt', __('٧+'), __('بنوك ومحافظ مدعومة')],
                    ['language', __('عربي/إنجليزي'), __('واجهة ثنائية اللغة')],
                    ['admin_panel_settings', __('أدوار وصلاحيات'), __('تحكّم دقيق بالفريق')],
                    ['insights', __('فورية'), __('تقارير لحظية')],
                ] as $i => [$icon, $big, $label])
                    <div class="reveal tonal-elevation flex flex-col items-center gap-3 rounded-3xl border border-outline-variant/40 bg-surface-container-lowest px-4 py-6 text-center" style="transition-delay: {{ $i * 90 }}ms">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-secondary-container/15 text-secondary">
                            <span class="material-symbols-outlined !text-2xl" data-weight="fill">{{ $icon }}</span>
                        </span>
                        <span class="font-display-lg text-2xl font-bold text-on-surface">{{ $big }}</span>
                        <span class="font-body-sm text-on-surface-variant">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ─────────────────────────  FEATURES  ───────────────────────── --}}
        <section id="features" class="scroll-mt-20 bg-surface py-24 md:py-28 lg:py-32">
            <div class="mx-auto max-w-7xl px-inline-md md:px-inline-lg">
            <div class="reveal mx-auto max-w-2xl text-center">
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-secondary">{{ __('كل ما تحتاجه') }}</p>
                <h2 class="mt-4 font-display-lg text-3xl font-bold text-on-surface sm:text-4xl lg:text-5xl">{{ __('مزايا مصمّمة لإدارة عملك بالكامل') }}</h2>
                <p class="mt-4 font-body-lg text-on-surface-variant">{{ __('من أول طلب حتى آخر تقرير - كل أدواتك في مساحة عمل واحدة أنيقة ومعزولة.') }}</p>
            </div>

            <div class="mt-20 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 lg:gap-8">
                @foreach ([
                    ['receipt_long', 'primary', __('طلبات سريعة'), __('أنشئ الطلبات في ثوانٍ عبر منشئ طلب ذكي يحسب المجموع تلقائياً من قائمتك.')],
                    ['account_balance', 'secondary', __('البنوك والمحافظ'), __('ادعم الدفع نقداً أو عبر البنوك والمحافظ الإلكترونية مع تتبّع كامل لكل حوالة.')],
                    ['restaurant_menu', 'tertiary', __('قائمة الطعام'), __('نظّم أصنافك في فئات مرتّبة مع أسعار مرنة وتفعيل/إخفاء فوري لأي صنف.')],
                    ['assessment', 'secondary', __('تقارير احترافية'), __('تقارير فورية عن الإيرادات والنشاط اليومي والأسبوعي، وتصدير PDF بنقرة واحدة.')],
                    ['group', 'primary', __('الفريق والصلاحيات'), __('ادعُ فريقك بأدوار جاهزة (مالك/مدير/موظف) وصلاحيات دقيقة لكل إجراء.')],
                    ['contacts', 'tertiary', __('عملاء دائمون'), __('تعرّف تلقائياً على عملائك المتكررين وتابع سجل طلباتهم وإجمالي إنفاقهم.')],
                ] as $i => [$icon, $accent, $title, $desc])
                    @php $accentClasses = match ($accent) {
                        'secondary' => 'bg-secondary-container/15 text-secondary',
                        'tertiary' => 'bg-tertiary/10 text-tertiary',
                        default => 'bg-primary/10 text-primary',
                    }; @endphp
                    <div class="reveal tonal-elevation group rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-7" style="transition-delay: {{ $i * 80 }}ms">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl transition-transform duration-300 group-hover:scale-110 {{ $accentClasses }}">
                            <span class="material-symbols-outlined !text-3xl">{{ $icon }}</span>
                        </div>
                        <h3 class="mt-5 font-headline-md text-headline-md text-on-surface">{{ $title }}</h3>
                        <p class="mt-2 font-body-sm leading-relaxed text-on-surface-variant">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
            </div>
        </section>

        {{-- ─────────────────────────  HOW IT WORKS  ───────────────────────── --}}
        <section id="how" class="scroll-mt-20 bg-surface-container-low py-24 md:py-28 lg:py-32">
            <div class="mx-auto max-w-7xl px-inline-md md:px-inline-lg">
                <div class="reveal mx-auto max-w-2xl text-center">
                    <p class="font-label-caps text-label-caps uppercase tracking-wider text-secondary">{{ __('ابدأ خلال دقائق') }}</p>
                    <h2 class="mt-4 font-display-lg text-3xl font-bold text-on-surface sm:text-4xl lg:text-5xl">{{ __('ثلاث خطوات وأنت جاهز') }}</h2>
                </div>

                <div class="relative mt-20 grid gap-10 md:grid-cols-3">
                    <div class="pointer-events-none absolute top-10 hidden border-t-2 border-dashed border-outline-variant/60 md:block" style="inset-inline: 16%;"></div>
                    @foreach ([
                        ['person_add', __('أنشئ حسابك'), __('سجّل عملك في دقائق واحصل على مساحة عمل خاصة ومعزولة بالكامل.')],
                        ['tune', __('جهّز قائمتك وبنوكك'), __('أضف أصنافك وأسعارك، وبنوكك ومحافظك، ووسائل الدفع المتاحة.')],
                        ['rocket_launch', __('ابدأ استقبال الطلبات'), __('استقبل الطلبات وتابع إيراداتك ونشاطك لحظة بلحظة من لوحة تحكم واحدة.')],
                    ] as $i => [$icon, $title, $desc])
                        <div class="reveal relative text-center" style="transition-delay: {{ $i * 120 }}ms">
                            <div class="relative mx-auto flex h-20 w-20 items-center justify-center rounded-3xl bg-primary text-on-primary shadow-xl shadow-primary/25">
                                <span class="material-symbols-outlined !text-4xl">{{ $icon }}</span>
                                <span class="absolute -end-2 -top-2 flex h-8 w-8 items-center justify-center rounded-full bg-secondary-container font-numeral-lg text-sm font-bold text-on-secondary-fixed shadow-lg" dir="ltr">{{ $i + 1 }}</span>
                            </div>
                            <h3 class="mt-6 font-headline-md text-headline-md text-on-surface">{{ $title }}</h3>
                            <p class="mx-auto mt-2 max-w-xs font-body-sm leading-relaxed text-on-surface-variant">{{ $desc }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ─────────────────────────  PRICING  ───────────────────────── --}}
        <section id="pricing" class="scroll-mt-20 bg-surface py-24 md:py-28 lg:py-32">
            <div class="mx-auto max-w-7xl px-inline-md md:px-inline-lg">
            <div class="reveal mx-auto max-w-2xl text-center">
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-secondary">{{ __('أسعار بسيطة وشفّافة') }}</p>
                <h2 class="mt-4 font-display-lg text-3xl font-bold text-on-surface sm:text-4xl lg:text-5xl">{{ __('اختر الباقة المناسبة لعملك') }}</h2>
                <p class="mt-4 font-body-lg text-on-surface-variant">{{ __('ابدأ مجاناً، وارتقِ متى شئت. كل الباقات تشمل التحديثات والدعم.') }}</p>
            </div>

            <div class="mt-20 grid items-stretch gap-6 lg:grid-cols-3 lg:gap-8">
                @php
                    $plans = [
                        ['ar' => __('الأساسية'), 'price' => '0', 'unit' => __('مجاناً'), 'devices' => __('٣ أجهزة'), 'popular' => false,
                            'features' => [__('إدارة الطلبات'), __('قائمة الطعام'), __('التقارير الأساسية'), __('مستخدم واحد')]],
                        ['ar' => __('الاحترافية'), 'price' => '99', 'unit' => __('₪ / شهرياً'), 'devices' => __('١٠ أجهزة'), 'popular' => true,
                            'features' => [__('كل مزايا الأساسية'), __('البنوك والمحافظ'), __('المصروفات'), __('الفريق والصلاحيات'), __('تقارير متقدمة')]],
                        ['ar' => __('المؤسسات'), 'price' => '299', 'unit' => __('₪ / شهرياً'), 'devices' => __('٥٠ جهازاً'), 'popular' => false,
                            'features' => [__('كل مزايا الاحترافية'), __('إدارة الأجهزة'), __('الثوابت المخصّصة'), __('النسخ الاحتياطي'), __('دعم ذو أولوية')]],
                    ];
                @endphp

                @foreach ($plans as $i => $plan)
                    <div class="reveal relative flex flex-col rounded-3xl p-8 transition-all
                        {{ $plan['popular']
                            ? 'border-2 border-secondary-container bg-surface-container-lowest shadow-2xl shadow-secondary-container/20 lg:-translate-y-4 lg:p-10'
                            : 'tonal-elevation border border-outline-variant/50 bg-surface-container-lowest' }}"
                        style="transition-delay: {{ $i * 110 }}ms">

                        @if ($plan['popular'])
                            <span class="absolute -top-3.5 start-1/2 -translate-x-1/2 rounded-full bg-secondary-container px-4 py-1 font-label-caps text-label-caps font-bold uppercase tracking-wider text-on-secondary-fixed shadow-lg">
                                {{ __('الأكثر شيوعاً') }}
                            </span>
                        @endif

                        <h3 class="font-headline-md text-headline-md text-on-surface">{{ $plan['ar'] }}</h3>
                        <div class="mt-4 flex items-end gap-2">
                            <span class="font-display-lg text-5xl font-bold text-on-surface" dir="ltr">{{ $plan['price'] }}</span>
                            <span class="mb-2 font-body-sm text-on-surface-variant">{{ $plan['unit'] }}</span>
                        </div>
                        <p class="mt-2 font-body-sm text-outline">{{ __('حتى') }} {{ $plan['devices'] }}</p>

                        <ul class="mt-8 flex-1 space-y-3">
                            @foreach ($plan['features'] as $feature)
                                <li class="flex items-center gap-3">
                                    <span class="material-symbols-outlined !text-xl {{ $plan['popular'] ? 'text-secondary' : 'text-primary' }}" data-weight="fill">check_circle</span>
                                    <span class="font-body-sm text-on-surface-variant">{{ $feature }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <a href="{{ route('signup') }}"
                            class="mt-8 flex items-center justify-center gap-2 rounded-xl px-6 py-3.5 font-headline-md font-bold transition-all active:scale-[0.98]
                            {{ $plan['popular']
                                ? 'bg-secondary-container text-on-secondary-fixed shadow-lg hover:brightness-105'
                                : 'bg-primary text-on-primary shadow-lg shadow-primary/20 hover:bg-primary-container hover:text-on-primary-container' }}" title="{{ __('ابدأ الآن') }}">
                            {{ __('ابدأ الآن') }}
                        </a>
                    </div>
                @endforeach
            </div>
            </div>
        </section>

        {{-- ─────────────────────────  CTA BAND  ───────────────────────── --}}
        <section class="mx-auto max-w-7xl px-inline-md py-24 md:px-inline-lg md:py-32">
            <div class="reveal relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-[#00236f] to-[#1e3a8a] px-8 py-16 text-center shadow-2xl sm:py-20 md:px-16 md:py-24">
                <div class="pointer-events-none absolute -top-1/3 start-[-5%] h-72 w-72 rounded-full bg-white/10 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-1/2 end-[-5%] h-80 w-80 rounded-full bg-secondary-container/20 blur-3xl"></div>
                <div class="relative">
                    <h2 class="font-display-lg text-3xl font-bold text-white sm:text-4xl md:text-5xl">{{ __('جاهز لتنمية عملك؟') }}</h2>
                    <p class="mx-auto mt-4 max-w-xl font-body-lg text-white/80">{{ __('انضم إلى الأعمال التي تدير طلباتها وحوالاتها باحترافية. جهّز مساحتك خلال دقائق.') }}</p>
                    <a href="{{ route('signup') }}"
                        {{-- text-[#00236f] not text-primary: the band behind it is a FIXED dark gradient in
                             both themes, but --c-primary flips to pale lavender in dark, which would land
                             at ~1.5:1 on this white button. Pinned to the same navy the gradient starts at. --}}
                        class="group mt-8 inline-flex items-center gap-2 rounded-xl bg-white px-8 py-4 font-headline-md text-headline-md font-bold text-[#00236f] shadow-xl transition-all hover:bg-white/90 active:scale-[0.98]" title="{{ __('أنشئ حسابك المجاني') }}">
                        <span>{{ __('أنشئ حسابك المجاني') }}</span>
                        <span class="material-symbols-outlined rtl:-scale-x-100 transition-transform group-hover:translate-x-1 rtl:group-hover:-translate-x-1">arrow_forward</span>
                    </a>
                </div>
            </div>
        </section>
    </main>

    {{-- ─────────────────────────  FOOTER  ───────────────────────── --}}
    <footer class="border-t border-outline-variant/60 bg-surface-container-lowest">
        <div class="mx-auto max-w-7xl px-inline-md py-16 md:px-inline-lg md:py-20">
            <div class="grid gap-10 md:grid-cols-[1.5fr_1fr_1fr_1fr] md:gap-12">
                <div>
                    <div class="flex items-center gap-3">
                        <x-logo />
                        <span class="font-display-lg text-2xl font-bold text-primary">{{ __('حوالاتي') }}</span>
                    </div>
                    <p class="mt-4 max-w-xs font-body-sm leading-relaxed text-on-surface-variant">
                        {{ __('منصّة عربية متكاملة لإدارة الطلبات والحوالات للمطاعم والأعمال - بسرعة وأمان.') }}
                    </p>
                </div>

                @foreach ([
                    __('المنتج') => [[__('المزايا'), '#features'], [__('كيف يعمل'), '#how'], [__('الأسعار'), '#pricing']],
                    __('الحساب') => [[__('تسجيل الدخول'), route('login')], [__('إنشاء حساب'), route('signup')]],
                    __('الدعم') => [[__('مركز المساعدة'), '/help'], [__('تواصل معنا'), 'mailto:support@hawalaty.test']],
                ] as $group => $links)
                    <div>
                        <h4 class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ $group }}</h4>
                        <ul class="mt-4 space-y-2.5">
                            @foreach ($links as [$label, $href])
                                <li><a href="{{ $href }}" class="font-body-sm text-on-surface-variant transition-colors hover:text-primary">{{ $label }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>

            <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t border-outline-variant/60 pt-8 md:flex-row">
                <span class="font-body-sm text-outline">{{ __('© 2026 حوالاتي. جميع الحقوق محفوظة.') }}</span>
                <span class="font-body-sm text-outline">{{ __('صُمّم بعناية للأعمال العربية.') }}</span>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var els = document.querySelectorAll('.reveal:not(.is-visible)');
            if (!('IntersectionObserver' in window)) {
                els.forEach(function (el) { el.classList.add('is-visible'); });
                return;
            }
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        io.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
            els.forEach(function (el) { io.observe(el); });
        });
    </script>
</body>
</html>
