@php
    $otherLocale = app()->getLocale() === 'ar' ? 'en' : 'ar';

    $trades = [
        ['restaurant', __('مطاعم ومقاهي')],
        ['storefront', __('بقالة وسوبرماركت')],
        ['local_gas_station', __('محطات محروقات')],
        ['build', __('ورش وصيانة')],
        ['local_laundry_service', __('مغاسل وخدمات')],
        ['medical_services', __('عيادات ومراكز')],
        ['checkroom', __('متاجر ملابس')],
        ['two_wheeler', __('توصيل ومندوبين')],
        ['account_balance', __('صرافة وحوالات')],
        ['nutrition', __('خضار وفواكه')],
    ];

    $stats = [
        ['bolt', 8, '', __('ثوانٍ لتسجيل طلب كامل')],
        ['language', 2, '', __('لغتان في الواجهة نفسها')],
        ['groups', 50, '+', __('عضو فريق بصلاحيات دقيقة')],
        ['cloud_off', 100, '%', __('يعمل حتى بلا إنترنت')],
    ];

    $tour = [
        [
            'key' => 'orders',
            'icon' => 'receipt_long',
            'title' => __('الطلبات'),
            'lead' => __('اختر الأصناف من قائمتك، فيُحسب المجموع فوراً وتُسجَّل طريقة الدفع - نقداً أو تحويلاً - في الحركة نفسها.'),
            'points' => [__('منشئ طلب يحسب المجموع تلقائياً'), __('نقداً أو تحويل بنكي أو محفظة'), __('إيصال جاهز للطباعة الحرارية')],
        ],
        [
            'key' => 'accounts',
            'icon' => 'account_balance',
            'title' => __('الحسابات والمحافظ'),
            'lead' => __('كل بنك ومحفظة وصندوق نقدي له رصيده الخاص، ويتحدّث لحظة تسجيل أي حركة - بلا جدول خارجي ولا مطابقة يدوية.'),
            'points' => [__('رصيد لحظي لكل حساب'), __('تحويل بين الحسابات بخطوة واحدة'), __('مطابقة تكشف أي فرق فوراً')],
        ],
        [
            'key' => 'reports',
            'icon' => 'insights',
            'title' => __('التقارير'),
            'lead' => __('الإيرادات والمصروفات وصافي الربح ونشاط الفريق - محسوبة لحظياً، لا في نهاية الشهر.'),
            'points' => [__('إيرادات يومية وأسبوعية وشهرية'), __('أعلى الأصناف مبيعاً'), __('تصدير PDF و CSV بنقرة')],
        ],
        [
            'key' => 'team',
            'icon' => 'admin_panel_settings',
            'title' => __('الفريق والصلاحيات'),
            'lead' => __('الموظف يستقبل الطلبات دون أن يرى الأرباح، والمحاسب يرى الأرقام دون أن يعدّل الأسعار.'),
            'points' => [__('أدوار جاهزة وصلاحيات مستقلة'), __('كل صلاحية تُفحص على الخادم'), __('سجل نشاط يوثّق كل إجراء')],
        ],
    ];

    $features = [
        ['receipt_long', 'primary', __('طلبات في ثوانٍ'), __('منشئ طلب ذكي يقرأ قائمتك ويحسب المجموع والباقي تلقائياً، مع بحث فوري عن الصنف أو العميل.')],
        ['account_balance_wallet', 'secondary', __('حسابات ومحافظ وصناديق'), __('نقداً أو بنكاً أو محفظة إلكترونية - رصيد كل حساب يتحدّث لحظياً مع كل حركة تُسجَّل.')],
        ['inventory_2', 'tertiary', __('كتالوج لأي نشاط'), __('فئات وأصناف وأسعار مرنة، وأيقونات جاهزة لكل مهنة - من مطعم إلى محطة وقود إلى ورشة.')],
        ['insights', 'secondary', __('تقارير لحظية'), __('إيرادات ومصروفات وصافي ربح وأعلى الأصناف، محسوبة الآن لا في نهاية الشهر، مع تصدير PDF و CSV.')],
        ['admin_panel_settings', 'primary', __('صلاحيات دقيقة'), __('أدوار جاهزة وصلاحية مستقلة لكل إجراء، مفحوصة على الخادم لا في المتصفح.')],
        ['contacts', 'tertiary', __('عملاء دائمون'), __('يتعرّف النظام على عملائك المتكررين تلقائياً ويتابع سجل طلباتهم وإجمالي إنفاقهم.')],
        ['cloud_off', 'primary', __('يعمل بلا إنترنت'), __('انقطاع الشبكة لا يوقف مشروعك: الطلبات تُحفظ محلياً وتُرسَل تلقائياً فور عودة الاتصال.')],
        ['sms', 'secondary', __('إشعارات واتساب'), __('رسالة تلقائية عند تسجيل طلب مدفوع بتحويل، تصل إلى الرقم الذي تحدّده أنت.')],
        ['language', 'tertiary', __('عربي وإنجليزي'), __('واجهة كاملة بالاتجاهين، لا ترجمة نصف مُنجزة - بتبديل فوري من أي صفحة.')],
    ];

    $steps = [
        ['person_add', __('أنشئ حسابك'), __('سجّل مشروعك في دقائق واحصل على مساحة عمل خاصة ومعزولة بقاعدة بيانات مستقلة.')],
        ['tune', __('جهّز كتالوجك وحساباتك'), __('أضف أصنافك وأسعارك، وبنوكك ومحافظك وصناديقك، وادعُ فريقك بالصلاحيات التي تريدها.')],
        ['rocket_launch', __('ابدأ التشغيل'), __('استقبل الطلبات وتابع الأرصدة والأرباح لحظة بلحظة من لوحة تحكم واحدة.')],
    ];

    $comparison = [
        [__('دفتر وقلم وملف إكسل منفصل'), __('كل شيء في مكان واحد يتحدّث لحظياً')],
        [__('رصيد البنك تعرفه آخر الشهر'), __('رصيد كل حساب أمامك الآن')],
        [__('لا تعرف من عدّل ماذا'), __('سجل نشاط يوثّق كل إجراء بالاسم والوقت')],
        [__('الموظف يرى كل شيء أو لا شيء'), __('صلاحية مستقلة لكل إجراء')],
        [__('انقطاع الإنترنت يوقف العمل'), __('الطلبات تُحفظ وتُرسَل تلقائياً')],
        [__('تقرير يحتاج ساعة عمل'), __('تقرير جاهز للتصدير بنقرة')],
    ];

    $faqs = [
        [__('هل حوالاتي مخصّص للمطاعم فقط؟'), __('لا. الكتالوج مبني ليخدم أي نشاط: بقالة، محطة وقود، ورشة، مغسلة، عيادة، متجر ملابس، أو مكتب صرافة. المطعم مجرد مثال واحد، ولكل مهنة أيقوناتها وفئاتها الجاهزة.')],
        [__('هل بيانات مشروعي معزولة عن غيري؟'), __('نعم، وبعزل فعلي: لكل حساب قاعدة بياناته المستقلة، لا مجرد عمود يفصل الصفوف داخل جدول مشترك. تفاصيل ذلك في صفحة الأمان.')],
        [__('ماذا يحدث إذا انقطع الإنترنت؟'), __('تستمر في العمل. الطلبات التي تُنشئها بلا اتصال تُحفظ على الجهاز وتُرسَل تلقائياً فور عودة الشبكة، ولن يُعرض تأكيد نجاح لطلب لم يصل إلى الخادم فعلاً.')],
        [__('هل أحتاج بطاقة ائتمان لأجرّب؟'), __('لا. التجربة مجانية لأربعة عشر يوماً بكامل المزايا وبدون أي بيانات دفع، ولن يُخصم منك شيء تلقائياً عند انتهائها.')],
        [__('هل يمكنني تصدير بياناتي إذا قرّرت المغادرة؟'), __('نعم، في أي وقت ودون الحاجة إلى مراسلة أحد: جداولك بصيغة CSV وتقاريرك وإيصالاتك بصيغة PDF، مباشرة من داخل التطبيق.')],
        [__('كم عدد الأجهزة وأعضاء الفريق المسموح بهم؟'), __('يعتمد على الباقة، من جهازين في تجربة البداية إلى عدد غير محدود في باقة إنتربرايز. يمكنك الترقية أو التخفيض في أي وقت دون فقدان أي بيانات.')],
        [__('هل تدعمون العربية والإنجليزية معاً؟'), __('نعم، الواجهة كاملة باللغتين وباتجاهي الكتابة، ويمكن لكل عضو في الفريق اختيار لغته الخاصة دون أن يؤثر ذلك على البقية.')],
        [__('كيف أنتقل من نظامي الحالي؟'), __('نساعدك في إدخال الكتالوج والحسابات وأرصدتها الافتتاحية. راسلنا عبر نموذج التواصل أدناه واذكر ما تستخدمه اليوم.')],
    ];

    $subjects = [
        'sales' => __('استفسار عن الأسعار'),
        'demo' => __('طلب عرض توضيحي'),
        'support' => __('دعم فني'),
        'partnership' => __('شراكة'),
        'other' => __('موضوع آخر'),
    ];

    $metaTitle = __('حوالاتي - نظام إدارة الطلبات والحسابات لأي مشروع');
    $metaDescription = __('نظام عربي متكامل لإدارة الطلبات والحسابات والمحافظ والمصروفات والتقارير لأي مشروع - بصلاحيات دقيقة، وعمل بلا إنترنت، وتجربة مجانية ١٤ يوماً.');

    $schemas = [
        [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => __('حوالاتي'),
            'url' => route('home'),
            'logo' => url('/icon-512.png'),
            'image' => url('/og-image.jpg'),
            'description' => $metaDescription,
            'contactPoint' => array_values(array_filter([
                $supportEmail ? [
                    '@type' => 'ContactPoint',
                    'contactType' => 'customer support',
                    'email' => $supportEmail,
                    'availableLanguage' => ['Arabic', 'English'],
                ] : null,
                $supportPhone ? [
                    '@type' => 'ContactPoint',
                    'contactType' => 'sales',
                    'telephone' => $supportPhone,
                    'availableLanguage' => ['Arabic', 'English'],
                ] : null,
            ])),
        ],
        [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => __('حوالاتي'),
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web, Android, iOS',
            'url' => route('home'),
            'description' => $metaDescription,
            'inLanguage' => ['ar', 'en'],
            'featureList' => array_map(fn ($f) => $f[2], $features),

            'offers' => [
                '@type' => 'AggregateOffer',
                'lowPrice' => '99',
                'highPrice' => '299',
                'priceCurrency' => 'ILS',
                'offerCount' => 4,
                'availability' => 'https://schema.org/InStock',
            ],
        ],
        [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn ($faq) => [
                '@type' => 'Question',
                'name' => $faq[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq[1]],
            ], $faqs),
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="lang-{{ app()->getLocale() }}"
    x-data="{
        dark: localStorage.getItem('theme') === 'dark',
        tipText: '', tipTop: 0, tipLeft: 0,
        showTipBelow(el, text) {

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

    <x-seo :title="$metaTitle" :description="$metaDescription" :canonical="route('home')" :schemas="$schemas" />

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <meta name="theme-color" content="#00236f">
    <x-fonts />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /*
         * Landing-only motion. Kept in the page rather than app.css because
         * nothing behind the login screen uses any of it, and app.css is the
         * bundle every authenticated request already pays for.
         */
        @keyframes floaty { 0%, 100% { transform: translateY(0) } 50% { transform: translateY(-14px) } }
        /* -100% of the track PLUS the 1rem that separates the two of them, so
           the second track lands on zero rather than one gap short of it. */
        @keyframes marquee { from { transform: translateX(0) } to { transform: translateX(calc(-100% - 1rem)) } }
        @keyframes pulse-ring { 0% { transform: scale(.85); opacity: .7 } 70% { transform: scale(1.6); opacity: 0 } 100% { opacity: 0 } }
        @keyframes tick-in { from { opacity: 0; transform: translateY(8px) } to { opacity: 1; transform: none } }

        .floaty { animation: floaty 6s ease-in-out infinite }
        .tick-in { animation: tick-in .45s cubic-bezier(.16,1,.3,1) both }

        .reveal { opacity: 0; transform: translateY(26px); transition: opacity .7s cubic-bezier(.16,1,.3,1), transform .7s cubic-bezier(.16,1,.3,1) }
        .reveal.is-visible { opacity: 1; transform: none }

        .grid-dots { background-image: radial-gradient(rgb(var(--c-outline-variant) / .55) 1px, transparent 1px); background-size: 30px 30px }

        /*
         * The trade strip: TWO identical tracks side by side, each translating
         * by its own full width plus the gap between them. When track one has
         * left the frame, track two is sitting exactly where it started, so the
         * loop has no seam and the strip is never empty.
         *
         * `min-width: 100%` is what makes that true at any window size. The
         * earlier version was one track holding the list twice and translating
         * -50%: that only fills the frame while the viewport is narrower than a
         * single copy of the list, and on a wide screen the tail end of the
         * strip scrolled past and left blank space behind it.
         *
         * `direction: ltr` on the containers because the animation is a
         * PHYSICAL translate - in an RTL flex row the second track would be
         * laid out to the left and the transform would carry it away from the
         * frame instead of into it. The chips get the page's own direction back
         * below, so their icon and label still sit the right way round.
         */
        .marquee { direction: ltr; display: flex; gap: 1rem; mask-image: linear-gradient(to right, transparent, #000 8%, #000 92%, transparent) }
        .marquee-track {
            display: flex; align-items: center; gap: 1rem;
            flex-shrink: 0; min-width: 100%; justify-content: space-around;
            animation: marquee 38s linear infinite;
        }
        [dir='rtl'] .marquee-track > li { direction: rtl }
        .marquee:hover .marquee-track { animation-play-state: paused }

        .pulse-ring::before {
            content: ''; position: absolute; inset: 0; border-radius: 9999px;
            background: currentColor; animation: pulse-ring 2.4s cubic-bezier(.16,1,.3,1) infinite;
        }

        /* Reading-progress bar. Width is written by script from scroll position;
           `transform` would be cheaper but a scaled bar blurs its own edge. */
        .progress-bar { transition: width .1s linear }

        /* FAQ uses <details>, so it opens with zero JavaScript and stays usable
           if the script never runs. Only the marker and the chevron are styled. */
        details > summary { list-style: none }
        details > summary::-webkit-details-marker { display: none }
        details[open] > summary .faq-chevron { transform: rotate(180deg) }

        @media (prefers-reduced-motion: reduce) {
            .reveal { opacity: 1; transform: none; transition: none }
            .floaty, .marquee-track, .pulse-ring::before, .tick-in { animation: none }
        }
    </style>
</head>
<body class="min-h-screen bg-surface font-sans text-on-surface antialiased">
    <x-skip-link />

    <header x-data="{ open: false }" class="sticky top-0 z-50 border-b border-outline-variant/60 bg-surface/80 backdrop-blur-lg">
        <div class="absolute inset-x-0 bottom-0 h-0.5 bg-outline-variant/20">
            <div id="scroll-progress" class="progress-bar h-full w-0 bg-primary" role="presentation"></div>
        </div>

        <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between px-inline-md md:h-20 md:px-inline-lg" aria-label="{{ __('التنقل الرئيسي') }}">
            <a href="{{ url('/') }}" class="flex items-center gap-3" title="{{ __('حوالاتي') }}">
                <x-logo />
                <span class="font-display-lg text-2xl font-bold leading-none text-primary">{{ __('حوالاتي') }}</span>
            </a>

            <div class="hidden items-center gap-8 lg:flex">
                @foreach ([
                    ['#tour', __('ما هو حوالاتي')],
                    ['#features', __('المزايا')],
                    ['#how', __('كيف يعمل')],
                    ['#pricing', __('الأسعار')],
                    [route('security'), __('الأمان')],
                    ['#faq', __('الأسئلة')],
                ] as [$href, $label])
                    <a href="{{ $href }}" class="font-body-sm text-on-surface-variant transition-colors hover:text-primary" title="{{ $label }}">{{ $label }}</a>
                @endforeach
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('locale.switch', $otherLocale) }}"
                    class="flex h-10 min-w-10 items-center justify-center rounded-full px-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                    aria-label="{{ __('تغيير اللغة') }}" title="{{ __('تغيير اللغة') }}">
                    {{ $otherLocale === 'en' ? 'EN' : 'ع' }}
                </a>
                <button type="button" @click="dark = !dark"
                    @mouseenter="showTipBelow($el, dark ? @js(__('الوضع الفاتح')) : @js(__('الوضع الداكن')))" @mouseleave="hideTip()"
                    @focus="showTipBelow($el, dark ? @js(__('الوضع الفاتح')) : @js(__('الوضع الداكن')))" @blur="hideTip()"
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
                <button type="button" @click="hideTip(); open = !open"
                    @mouseenter="showTipBelow($el, @js(__('القائمة')))" @mouseleave="hideTip()"
                    @focus="showTipBelow($el, @js(__('القائمة')))" @blur="hideTip()"
                    class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high lg:hidden"
                    :aria-expanded="open ? 'true' : 'false'" aria-controls="mobile-menu"
                    aria-label="{{ __('القائمة') }}" title="{{ __('القائمة') }}">
                    <span class="material-symbols-outlined" x-text="open ? 'close' : 'menu'"></span>
                </button>
            </div>
        </nav>

        <div id="mobile-menu" x-show="open" x-cloak class="border-t border-outline-variant/60 bg-surface px-inline-md py-4 lg:hidden" style="display:none">
            <div class="flex flex-col gap-1">
                @foreach ([
                    ['#tour', __('ما هو حوالاتي')],
                    ['#features', __('المزايا')],
                    ['#how', __('كيف يعمل')],
                    ['#pricing', __('الأسعار')],
                    [route('security'), __('الأمان')],
                    ['#faq', __('الأسئلة')],
                    ['#contact', __('تواصل معنا')],
                ] as [$href, $label])
                    <a href="{{ $href }}" @click="open = false" class="rounded-lg px-3 py-2.5 font-body-lg text-on-surface-variant hover:bg-surface-container" title="{{ $label }}">{{ $label }}</a>
                @endforeach
                <hr class="my-2 border-outline-variant/60">
                <a href="{{ route('login') }}" class="rounded-lg px-3 py-2.5 font-body-lg font-bold text-primary hover:bg-surface-container" title="{{ __('تسجيل الدخول') }}">{{ __('تسجيل الدخول') }}</a>
                <a href="{{ route('signup') }}" class="mt-1 rounded-xl bg-primary px-3 py-2.5 text-center font-bold text-on-primary" title="{{ __('ابدأ مجاناً') }}">{{ __('ابدأ مجاناً') }}</a>
            </div>
        </div>
    </header>

    <main id="main">
        <section class="relative overflow-hidden">
            <div class="pointer-events-none absolute inset-0 grid-dots opacity-60" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -top-40 start-[-10%] h-[28rem] w-[28rem] rounded-full bg-primary/20 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-52 end-[-8%] h-[26rem] w-[26rem] rounded-full bg-secondary-container/25 blur-3xl" aria-hidden="true"></div>

            <div class="relative mx-auto grid max-w-7xl items-center gap-16 px-inline-md py-20 md:px-inline-lg md:py-28 lg:grid-cols-[1.05fr_0.95fr] lg:gap-20 lg:py-32">
                <div class="page-enter text-center lg:text-start">
                    <span class="inline-flex items-center gap-2 rounded-full border border-outline-variant bg-surface-container-low px-4 py-1.5 font-body-sm text-on-surface-variant">
                        <span class="relative flex h-2 w-2 text-secondary">
                            <span class="pulse-ring absolute inset-0" aria-hidden="true"></span>
                            <span class="relative h-2 w-2 rounded-full bg-secondary-container"></span>
                        </span>
                        {{ __('نظام عربي ثنائي اللغة لأي مشروع') }}
                    </span>

                    <h1 class="mt-8 font-display-lg text-4xl font-bold leading-[1.1] tracking-tight text-on-surface sm:text-5xl md:text-6xl lg:text-[64px]">
                        {{ __('أدر مشروعك كله') }}
                        <span class="text-primary">{{ __('من شاشة واحدة') }}</span>
                    </h1>

                    <p class="mx-auto mt-7 max-w-2xl font-body-lg leading-relaxed text-on-surface-variant lg:mx-0">
                        {{ __('الطلبات، والحسابات والمحافظ، والكتالوج، والمصروفات، والتقارير - كلها في نظام واحد يتحدّث لحظياً، بصلاحيات دقيقة لفريقك، ويكمل العمل حتى لو انقطع الإنترنت.') }}
                    </p>

                    <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row lg:justify-start">
                        <a href="{{ route('signup') }}"
                            class="group flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-8 py-4 font-headline-md text-headline-md text-on-primary shadow-xl shadow-primary/25 transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-[0.98] sm:w-auto" title="{{ __('ابدأ التجربة المجانية') }}">
                            <span>{{ __('ابدأ التجربة المجانية') }}</span>
                            <span class="material-symbols-outlined rtl:-scale-x-100 transition-transform group-hover:translate-x-1 rtl:group-hover:-translate-x-1">arrow_forward</span>
                        </a>
                        <a href="#tour"
                            class="flex w-full items-center justify-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-8 py-4 font-headline-md text-headline-md text-on-surface transition-all hover:bg-surface-container active:scale-[0.98] sm:w-auto" title="{{ __('شاهد كيف يعمل') }}">
                            <span class="material-symbols-outlined text-primary" data-weight="fill">play_circle</span>
                            <span>{{ __('شاهد كيف يعمل') }}</span>
                        </a>
                    </div>

                    <ul class="mt-7 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 font-body-sm text-outline lg:justify-start">
                        @foreach ([__('١٤ يوماً مجاناً'), __('بدون بطاقة ائتمان'), __('إلغاء في أي وقت')] as $promise)
                            <li class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined !text-base text-primary" data-weight="fill" aria-hidden="true">check_circle</span>{{ $promise }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="reveal is-visible relative mx-auto w-full max-w-lg lg:mx-0 lg:max-w-none" aria-hidden="true">
                    <div class="pointer-events-none absolute -inset-4 -z-10 hidden rounded-[2.5rem] bg-primary-container/25 blur-2xl lg:block"></div>
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

        <section class="border-y border-outline-variant/50 bg-surface-container-low py-10">
            <p class="mb-6 text-center font-label-caps text-label-caps uppercase tracking-wider text-outline">{{ __('مصمَّم ليخدم أي نشاط تجاري') }}</p>
            <div class="marquee overflow-hidden">
                @foreach ([false, true] as $isDuplicate)
                    <ul class="marquee-track" @if ($isDuplicate) aria-hidden="true" @endif>
                        @foreach ($trades as [$icon, $label])
                            <li class="flex flex-shrink-0 items-center gap-2.5 rounded-2xl border border-outline-variant/50 bg-surface-container-lowest px-5 py-3">
                                <span class="material-symbols-outlined !text-xl text-primary">{{ $icon }}</span>
                                <span class="whitespace-nowrap font-body-sm font-bold text-on-surface-variant">{{ $label }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </div>
        </section>

        <section class="bg-surface">
            <div class="mx-auto grid max-w-7xl grid-cols-2 gap-4 px-inline-md py-14 md:grid-cols-4 md:gap-6 md:px-inline-lg md:py-16">
                @foreach ($stats as $i => [$icon, $value, $suffix, $label])
                    <div class="reveal tonal-elevation flex flex-col items-center gap-3 rounded-3xl border border-outline-variant/40 bg-surface-container-lowest px-4 py-6 text-center" style="transition-delay: {{ $i * 90 }}ms">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-secondary-container/15 text-secondary">
                            <span class="material-symbols-outlined !text-2xl" data-weight="fill">{{ $icon }}</span>
                        </span>
                        <span class="font-display-lg text-3xl font-bold text-on-surface" dir="ltr">
                            <span class="count" data-count="{{ $value }}">{{ $value }}</span>{{ $suffix }}
                        </span>
                        <span class="font-body-sm text-on-surface-variant">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section id="tour" class="scroll-mt-24 border-y border-outline-variant/50 bg-surface-container-low py-24 md:py-28 lg:py-32">
            <div class="mx-auto max-w-7xl px-inline-md md:px-inline-lg">
                <div class="reveal mx-auto max-w-2xl text-center">
                    <p class="font-label-caps text-label-caps uppercase tracking-wider text-secondary">{{ __('جولة سريعة') }}</p>
                    <h2 class="mt-4 font-display-lg text-3xl font-bold text-on-surface sm:text-4xl lg:text-5xl">{{ __('ما هو حوالاتي، وكيف يعمل؟') }}</h2>
                    <p class="mt-4 font-body-lg text-on-surface-variant">{{ __('أربع شاشات تختصر يوم عمل كامل. اضغط على أي منها لترى ما تفعله.') }}</p>
                </div>

                <div class="reveal mt-16"
                    x-data="{
                        tab: 0,
                        timer: null,
                        get reduced() { return window.matchMedia('(prefers-reduced-motion: reduce)').matches },
                        start() {
                            if (this.reduced) return;
                            this.stop();

                            this.timer = setInterval(() => { this.tab = (this.tab + 1) % {{ count($tour) }} }, 5000);
                        },
                        stop() { if (this.timer) { clearInterval(this.timer); this.timer = null } },
                        select(i) { this.stop(); this.tab = i },
                    }"
                    x-init="start()" @mouseenter="stop()">

                    <div class="grid gap-8 lg:grid-cols-[0.85fr_1.15fr] lg:gap-12">
                        <div class="flex gap-3 overflow-x-auto pb-2 lg:flex-col lg:overflow-visible lg:pb-0" role="tablist" aria-label="{{ __('جولة في النظام') }}">
                            @foreach ($tour as $i => $panel)
                                <button type="button" role="tab" id="tour-tab-{{ $panel['key'] }}"
                                    aria-controls="tour-panel-{{ $panel['key'] }}"
                                    :aria-selected="tab === {{ $i }} ? 'true' : 'false'"
                                    :tabindex="tab === {{ $i }} ? 0 : -1"
                                    @click="select({{ $i }})"
                                    @keydown.arrow-down.prevent="select(({{ $i }} + 1) % {{ count($tour) }}); $el.parentElement.children[({{ $i }} + 1) % {{ count($tour) }}].focus()"
                                    @keydown.arrow-up.prevent="select(({{ $i }} + {{ count($tour) - 1 }}) % {{ count($tour) }}); $el.parentElement.children[({{ $i }} + {{ count($tour) - 1 }}) % {{ count($tour) }}].focus()"
                                    class="flex min-w-[15rem] flex-shrink-0 items-start gap-4 rounded-2xl border p-5 text-start transition-all lg:min-w-0"
                                    :class="tab === {{ $i }}
                                        ? 'border-primary/40 bg-surface-container-lowest shadow-lg'
                                        : 'border-outline-variant/50 bg-transparent hover:bg-surface-container-lowest/60'">
                                    <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl transition-colors"
                                        :class="tab === {{ $i }} ? 'bg-primary text-on-primary' : 'bg-primary/10 text-primary'">
                                        <span class="material-symbols-outlined !text-xl">{{ $panel['icon'] }}</span>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block font-headline-md text-body-lg font-bold text-on-surface">{{ $panel['title'] }}</span>
                                        <span class="mt-1 block font-body-sm leading-relaxed text-on-surface-variant" x-show="tab === {{ $i }}" x-collapse>{{ $panel['lead'] }}</span>
                                    </span>
                                </button>
                            @endforeach
                        </div>

                        <div class="relative min-h-[26rem]">
                            @foreach ($tour as $i => $panel)
                                <div role="tabpanel" id="tour-panel-{{ $panel['key'] }}" aria-labelledby="tour-tab-{{ $panel['key'] }}"
                                    x-show="tab === {{ $i }}" x-cloak style="display:none"
                                    x-transition:enter="transition ease-out duration-300"
                                    x-transition:enter-start="opacity-0 translate-y-3"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                    class="tonal-elevation h-full rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-6 md:p-8">

                                    @include('partials._tour-mock', ['panel' => $panel['key']])

                                    <ul class="mt-6 space-y-2.5 border-t border-outline-variant/50 pt-5">
                                        @foreach ($panel['points'] as $point)
                                            <li class="flex items-center gap-2.5 font-body-sm text-on-surface-variant">
                                                <span class="material-symbols-outlined !text-base text-primary" data-weight="fill" aria-hidden="true">check_circle</span>{{ $point }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="features" class="scroll-mt-24 bg-surface py-24 md:py-28 lg:py-32">
            <div class="mx-auto max-w-7xl px-inline-md md:px-inline-lg">
                <div class="reveal mx-auto max-w-2xl text-center">
                    <p class="font-label-caps text-label-caps uppercase tracking-wider text-secondary">{{ __('كل ما تحتاجه') }}</p>
                    <h2 class="mt-4 font-display-lg text-3xl font-bold text-on-surface sm:text-4xl lg:text-5xl">{{ __('مزايا مصمّمة لإدارة مشروعك بالكامل') }}</h2>
                    <p class="mt-4 font-body-lg text-on-surface-variant">{{ __('من أول طلب حتى آخر تقرير - كل أدواتك في مساحة عمل واحدة أنيقة ومعزولة.') }}</p>
                </div>

                <div class="mt-20 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 lg:gap-8">
                    @foreach ($features as $i => [$icon, $accent, $title, $desc])
                        @php $accentClasses = match ($accent) {
                            'secondary' => 'bg-secondary-container/15 text-secondary',
                            'tertiary' => 'bg-tertiary/10 text-tertiary',
                            default => 'bg-primary/10 text-primary',
                        }; @endphp
                        <div class="reveal tonal-elevation group rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-7" style="transition-delay: {{ $i * 70 }}ms">
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

        <section class="border-y border-outline-variant/50 bg-surface-container-low py-24 md:py-28">
            <div class="mx-auto max-w-5xl px-inline-md md:px-inline-lg">
                <div class="reveal mx-auto max-w-2xl text-center">
                    <p class="font-label-caps text-label-caps uppercase tracking-wider text-secondary">{{ __('الفرق باختصار') }}</p>
                    <h2 class="mt-4 font-display-lg text-3xl font-bold text-on-surface sm:text-4xl lg:text-5xl">{{ __('يومك قبل وبعد') }}</h2>
                </div>

                <div class="reveal mt-14 overflow-hidden rounded-3xl border border-outline-variant/50">
                    <div class="grid grid-cols-2 bg-surface-container">
                        <div class="flex items-center gap-2 border-e border-outline-variant/50 px-5 py-4 md:px-8">
                            <span class="material-symbols-outlined !text-xl text-outline" aria-hidden="true">history_toggle_off</span>
                            <h3 class="font-headline-md text-body-lg font-bold text-on-surface-variant">{{ __('اليوم') }}</h3>
                        </div>
                        <div class="flex items-center gap-2 px-5 py-4 md:px-8">
                            <span class="material-symbols-outlined !text-xl text-primary" data-weight="fill" aria-hidden="true">verified</span>
                            <h3 class="font-headline-md text-body-lg font-bold text-primary">{{ __('مع حوالاتي') }}</h3>
                        </div>
                    </div>

                    @foreach ($comparison as $i => [$before, $after])
                        <div class="grid grid-cols-2 border-t border-outline-variant/50 {{ $i % 2 ? 'bg-surface-container-lowest' : 'bg-surface-container-low' }}">
                            <p class="flex items-start gap-2.5 border-e border-outline-variant/50 px-5 py-4 font-body-sm leading-relaxed text-on-surface-variant md:px-8">
                                <span class="material-symbols-outlined mt-0.5 flex-shrink-0 !text-base text-outline/70" aria-hidden="true">close</span>{{ $before }}
                            </p>
                            <p class="flex items-start gap-2.5 px-5 py-4 font-body-sm font-bold leading-relaxed text-on-surface md:px-8">
                                <span class="material-symbols-outlined mt-0.5 flex-shrink-0 !text-base text-primary" data-weight="fill" aria-hidden="true">check_circle</span>{{ $after }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="how" class="scroll-mt-24 bg-surface py-24 md:py-28 lg:py-32">
            <div class="mx-auto max-w-7xl px-inline-md md:px-inline-lg">
                <div class="reveal mx-auto max-w-2xl text-center">
                    <p class="font-label-caps text-label-caps uppercase tracking-wider text-secondary">{{ __('ابدأ خلال دقائق') }}</p>
                    <h2 class="mt-4 font-display-lg text-3xl font-bold text-on-surface sm:text-4xl lg:text-5xl">{{ __('ثلاث خطوات وأنت جاهز') }}</h2>
                </div>

                <div class="relative mt-20 grid gap-10 md:grid-cols-3">
                    <div class="pointer-events-none absolute top-10 hidden border-t-2 border-dashed border-outline-variant/60 md:block" style="inset-inline: 16%;" aria-hidden="true"></div>
                    @foreach ($steps as $i => [$icon, $title, $desc])
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

        <section class="border-y border-outline-variant/50 bg-surface-container-low py-24 md:py-28">
            <div class="mx-auto max-w-7xl px-inline-md md:px-inline-lg">
                <div class="reveal grid items-center gap-12 lg:grid-cols-[1fr_1fr] lg:gap-20">
                    <div>
                        <p class="font-label-caps text-label-caps uppercase tracking-wider text-secondary">{{ __('الأمان والخصوصية') }}</p>
                        <h2 class="mt-4 font-display-lg text-3xl font-bold text-on-surface sm:text-4xl">{{ __('أرقام مشروعك ليست بضاعة') }}</h2>
                        <p class="mt-4 font-body-lg leading-relaxed text-on-surface-variant">
                            {{ __('لكل حساب قاعدة بيانات مستقلة تماماً - عزل فعلي لا مجرد عمود يفصل الصفوف. ولا نبيع بياناتك ولا نستخدمها في الإعلانات ولا في تدريب نماذج ذكاء اصطناعي.') }}
                        </p>
                        <a href="{{ route('security') }}" class="group mt-8 inline-flex items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-6 py-3 font-headline-md font-bold text-on-surface transition-all hover:bg-surface-container active:scale-[0.98]" title="{{ __('اقرأ تفاصيل الأمان') }}">
                            <span class="material-symbols-outlined text-primary" aria-hidden="true">shield_person</span>
                            <span>{{ __('اقرأ تفاصيل الأمان') }}</span>
                            <span class="material-symbols-outlined rtl:-scale-x-100 transition-transform group-hover:translate-x-1 rtl:group-hover:-translate-x-1" aria-hidden="true">arrow_forward</span>
                        </a>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ([
                            ['dns', __('قاعدة مستقلة لكل حساب')],
                            ['lock', __('HTTPS وكلمات مرور مُجزَّأة')],
                            ['fact_check', __('سجل نشاط غير قابل للتعديل')],
                            ['backup', __('نسخ احتياطية دورية')],
                        ] as [$icon, $label])
                            <div class="tonal-elevation flex items-center gap-3 rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-5">
                                <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                    <span class="material-symbols-outlined !text-xl">{{ $icon }}</span>
                                </span>
                                <span class="font-body-sm font-bold text-on-surface">{{ $label }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section id="pricing" class="scroll-mt-24 bg-surface py-24 md:py-28 lg:py-32">
            <div class="mx-auto max-w-7xl px-inline-md md:px-inline-lg"
                x-data="{
                    annual: false,

                    price(monthly) { return this.annual ? monthly * 10 : monthly },
                }">

                <div class="reveal mx-auto max-w-2xl text-center">
                    <p class="font-label-caps text-label-caps uppercase tracking-wider text-secondary">{{ __('أسعار بسيطة وشفّافة') }}</p>
                    <h2 class="mt-4 font-display-lg text-3xl font-bold text-on-surface sm:text-4xl lg:text-5xl">{{ __('اختر الباقة المناسبة لمشروعك') }}</h2>
                    <p class="mt-4 font-body-lg text-on-surface-variant">{{ __('ابدأ مجاناً وارتقِ متى شئت. كل الباقات تشمل التحديثات والدعم، والترقية أو التخفيض لا يفقدك أي بيانات.') }}</p>
                </div>

                <div class="reveal mt-10 flex items-center justify-center gap-4">
                    <span class="font-body-sm font-bold transition-colors" :class="annual ? 'text-outline' : 'text-on-surface'">{{ __('شهري') }}</span>
                    <button type="button" @click="annual = !annual" role="switch" :aria-checked="annual ? 'true' : 'false'"
                        class="relative h-8 w-16 flex-shrink-0 rounded-full border border-outline-variant transition-colors"
                        :class="annual ? 'bg-primary' : 'bg-surface-container-high'"
                        aria-label="{{ __('التبديل بين الدفع الشهري والسنوي') }}">
                        <span class="absolute top-1 h-5 w-5 rounded-full bg-surface-container-lowest shadow transition-all"
                            :class="annual ? 'start-9' : 'start-1'"></span>
                    </button>
                    <span class="flex items-center gap-2 font-body-sm font-bold transition-colors" :class="annual ? 'text-on-surface' : 'text-outline'">
                        {{ __('سنوي') }}
                        <span class="rounded-full bg-secondary-container/20 px-2.5 py-0.5 font-label-caps text-label-caps font-bold text-secondary">{{ __('شهران مجاناً') }}</span>
                    </span>
                </div>

                @php
                    $plans = [
                        [

                            'name' => __('البداية'), 'monthly' => 0, 'devices' => __('جهازين'),
                            'tagline' => __('جرّب النظام كاملاً قبل أن تدفع'), 'popular' => false, 'enterprise' => false,
                            'features' => [__('إدارة الطلبات'), __('الكتالوج والأسعار'), __('التقارير الأساسية'), __('مستخدم واحد'), __('العمل بلا إنترنت')],
                        ],
                        [
                            'name' => __('الاحترافية'), 'monthly' => 99, 'devices' => __('١٠ أجهزة'),
                            'tagline' => __('لمشروع قائم بفريق صغير'), 'popular' => true, 'enterprise' => false,
                            'features' => [__('كل مزايا البداية'), __('الحسابات والمحافظ والصناديق'), __('المصروفات والمطابقة'), __('الفريق والصلاحيات'), __('تقارير متقدمة وتصدير'), __('إشعارات واتساب')],
                        ],
                        [
                            'name' => __('الأعمال'), 'monthly' => 299, 'devices' => __('٥٠ جهازاً'),
                            'tagline' => __('لعدة فروع وفريق كامل'), 'popular' => false, 'enterprise' => false,
                            'features' => [__('كل مزايا الاحترافية'), __('إدارة الأجهزة'), __('الثوابت المخصّصة'), __('النسخ الاحتياطي'), __('سجل نشاط موسّع'), __('دعم ذو أولوية')],
                        ],
                        [
                            'name' => __('إنتربرايز'), 'monthly' => null, 'devices' => __('أجهزة غير محدودة'),
                            'tagline' => __('لمؤسسة بمتطلبات خاصة'), 'popular' => false, 'enterprise' => true,
                            'features' => [__('كل مزايا الأعمال'), __('خادم مخصّص إن رغبت'), __('تكامل عبر الـ API'), __('اتفاقية مستوى خدمة (SLA)'), __('مدير حساب مخصّص'), __('تدريب وإعداد للفريق')],
                        ],
                    ];
                @endphp

                <div class="mt-14 grid items-stretch gap-6 md:grid-cols-2 xl:grid-cols-4 xl:gap-5">
                    @foreach ($plans as $i => $plan)
                        <div class="reveal relative flex flex-col rounded-3xl p-7 transition-all
                            {{ $plan['popular']
                                ? 'border-2 border-secondary-container bg-surface-container-lowest shadow-2xl shadow-secondary-container/20 xl:-translate-y-4'
                                : ($plan['enterprise']
                                    ? 'tonal-elevation border border-primary/30 bg-primary/[0.04]'
                                    : 'tonal-elevation border border-outline-variant/50 bg-surface-container-lowest') }}"
                            style="transition-delay: {{ $i * 90 }}ms">

                            @if ($plan['popular'])
                                <span class="absolute -top-3.5 start-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-secondary-container px-4 py-1 font-label-caps text-label-caps font-bold uppercase tracking-wider text-on-secondary-fixed shadow-lg rtl:translate-x-1/2">
                                    {{ __('الأكثر شيوعاً') }}
                                </span>
                            @endif

                            <div class="flex items-center gap-2">
                                @if ($plan['enterprise'])
                                    <span class="material-symbols-outlined !text-xl text-primary" data-weight="fill" aria-hidden="true">workspace_premium</span>
                                @endif
                                <h3 class="font-headline-md text-headline-md text-on-surface">{{ $plan['name'] }}</h3>
                            </div>
                            <p class="mt-1 font-body-sm text-outline">{{ $plan['tagline'] }}</p>

                            <div class="mt-5 flex min-h-[4.25rem] items-end gap-2">
                                @if ($plan['enterprise'])
                                    <span class="font-display-lg text-3xl font-bold text-on-surface">{{ __('تسعير مخصّص') }}</span>
                                @elseif ($plan['monthly'] === 0)
                                    <span class="font-display-lg text-5xl font-bold text-on-surface" dir="ltr">14</span>
                                    <span class="mb-2 font-body-sm text-on-surface-variant">{{ __('يوماً مجاناً') }}</span>
                                @else
                                    <span class="font-display-lg text-5xl font-bold text-on-surface" dir="ltr" x-text="price({{ $plan['monthly'] }})">{{ $plan['monthly'] }}</span>
                                    <span class="mb-2 font-body-sm text-on-surface-variant">
                                        <span x-show="!annual">{{ __('₪ / شهرياً') }}</span>
                                        <span x-show="annual" x-cloak style="display:none">{{ __('₪ / سنوياً') }}</span>
                                    </span>
                                @endif
                            </div>

                            <p class="mt-1 font-body-sm text-outline">{{ $plan['enterprise'] ? $plan['devices'] : __('حتى').' '.$plan['devices'] }}</p>

                            <ul class="mt-7 flex-1 space-y-3">
                                @foreach ($plan['features'] as $feature)
                                    <li class="flex items-start gap-3">
                                        <span class="material-symbols-outlined mt-0.5 flex-shrink-0 !text-xl {{ $plan['popular'] ? 'text-secondary' : 'text-primary' }}" data-weight="fill" aria-hidden="true">check_circle</span>
                                        <span class="font-body-sm text-on-surface-variant">{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>

                            @if ($plan['enterprise'])
                                <a href="#contact" data-plan="{{ $plan['name'] }}"
                                    class="plan-cta mt-8 flex items-center justify-center gap-2 rounded-xl border-2 border-primary px-6 py-3.5 font-headline-md font-bold text-primary transition-all hover:bg-primary hover:text-on-primary active:scale-[0.98]"
                                    title="{{ __('تحدّث مع فريق المبيعات') }}">
                                    <span class="material-symbols-outlined !text-xl" aria-hidden="true">support_agent</span>
                                    {{ __('تحدّث مع فريق المبيعات') }}
                                </a>
                            @else
                                <a href="{{ route('signup') }}"
                                    class="mt-8 flex items-center justify-center gap-2 rounded-xl px-6 py-3.5 font-headline-md font-bold transition-all active:scale-[0.98]
                                    {{ $plan['popular']
                                        ? 'bg-secondary-container text-on-secondary-fixed shadow-lg hover:brightness-105'
                                        : 'bg-primary text-on-primary shadow-lg shadow-primary/20 hover:bg-primary-container hover:text-on-primary-container' }}"
                                    title="{{ __('ابدأ الآن') }}">
                                    {{ __('ابدأ الآن') }}
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>

                <p class="reveal mt-8 text-center font-body-sm text-outline">{{ __('الأسعار بالشيكل وتشمل التحديثات والدعم. تحتاج فاتورة أو طريقة دفع مختلفة؟ راسلنا.') }}</p>
            </div>
        </section>

        <section id="faq" class="scroll-mt-24 border-y border-outline-variant/50 bg-surface-container-low py-24 md:py-28">
            <div class="mx-auto max-w-3xl px-inline-md md:px-inline-lg">
                <div class="reveal text-center">
                    <p class="font-label-caps text-label-caps uppercase tracking-wider text-secondary">{{ __('أسئلة متكرّرة') }}</p>
                    <h2 class="mt-4 font-display-lg text-3xl font-bold text-on-surface sm:text-4xl lg:text-5xl">{{ __('كل ما يُسأل عادةً قبل البدء') }}</h2>
                </div>

                <div class="reveal mt-12 space-y-3">
                    @foreach ($faqs as $i => [$question, $answer])
                        <details class="tonal-elevation group rounded-2xl border border-outline-variant/50 bg-surface-container-lowest" @if ($i === 0) open @endif>
                            <summary class="flex cursor-pointer items-center justify-between gap-4 p-5 font-headline-md text-body-lg font-bold text-on-surface">
                                <span>{{ $question }}</span>
                                <span class="material-symbols-outlined faq-chevron flex-shrink-0 text-primary transition-transform duration-300" aria-hidden="true">expand_more</span>
                            </summary>
                            <p class="border-t border-outline-variant/50 p-5 font-body-lg leading-relaxed text-on-surface-variant">{{ $answer }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="contact" class="scroll-mt-24 bg-surface py-24 md:py-28 lg:py-32">
            <div class="mx-auto max-w-7xl px-inline-md md:px-inline-lg">
                <div class="grid gap-12 lg:grid-cols-[0.9fr_1.1fr] lg:gap-16">
                    <div class="reveal">
                        <p class="font-label-caps text-label-caps uppercase tracking-wider text-secondary">{{ __('تواصل معنا') }}</p>
                        <h2 class="mt-4 font-display-lg text-3xl font-bold text-on-surface sm:text-4xl">{{ __('عندك سؤال؟ نجاوبك خلال يوم عمل') }}</h2>
                        <p class="mt-4 font-body-lg leading-relaxed text-on-surface-variant">
                            {{ __('اسأل عن الأسعار، أو اطلب عرضاً توضيحياً مباشراً على شاشتك، أو احكِ لنا عن نظامك الحالي ونساعدك في الانتقال منه.') }}
                        </p>

                        <ul class="mt-8 space-y-4">
                            @if ($supportEmail)
                                <li>
                                    <a href="mailto:{{ $supportEmail }}" class="tonal-elevation flex items-center gap-4 rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-4 transition-colors hover:border-primary/40">
                                        <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary"><span class="material-symbols-outlined !text-xl">mail</span></span>
                                        <span class="min-w-0">
                                            <span class="block font-body-sm text-outline">{{ __('البريد الإلكتروني') }}</span>
                                            <span class="block truncate font-body-lg font-bold text-on-surface" dir="ltr">{{ $supportEmail }}</span>
                                        </span>
                                    </a>
                                </li>
                            @endif
                            @if ($supportPhone)
                                <li>
                                    <a href="tel:{{ $supportPhone }}" class="tonal-elevation flex items-center gap-4 rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-4 transition-colors hover:border-primary/40">
                                        <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-secondary-container/15 text-secondary"><span class="material-symbols-outlined !text-xl">call</span></span>
                                        <span class="min-w-0">
                                            <span class="block font-body-sm text-outline">{{ __('الهاتف') }}</span>
                                            <span class="block truncate font-body-lg font-bold text-on-surface" dir="ltr">{{ $supportPhone }}</span>
                                        </span>
                                    </a>
                                </li>
                            @endif
                            <li class="flex items-center gap-4 rounded-2xl border border-dashed border-outline-variant/60 p-4">
                                <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-tertiary/10 text-tertiary"><span class="material-symbols-outlined !text-xl">schedule</span></span>
                                <span class="min-w-0">
                                    <span class="block font-body-sm text-outline">{{ __('وقت الرد') }}</span>
                                    <span class="block font-body-lg font-bold text-on-surface">{{ __('خلال يوم عمل واحد') }}</span>
                                </span>
                            </li>
                        </ul>
                    </div>

                    <div class="reveal tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-6 md:p-8">
                        @if (session('success'))
                            <div class="mb-6 flex items-start gap-3 rounded-2xl border border-success/30 bg-success-container/15 p-4" role="status">
                                <span class="material-symbols-outlined text-success" data-weight="fill" aria-hidden="true">check_circle</span>
                                <p class="font-body-sm font-bold text-on-surface">{{ session('success') }}</p>
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="mb-6 flex items-start gap-3 rounded-2xl border border-error/30 bg-error-container/25 p-4" role="alert">
                                <span class="material-symbols-outlined text-error" aria-hidden="true">error</span>
                                <div class="font-body-sm text-on-error-container">
                                    <p class="font-bold">{{ __('يرجى تصحيح الأخطاء التالية:') }}</p>
                                    <ul class="mt-1 list-disc ps-5">
                                        @foreach ($errors->all() as $message)
                                            <li>{{ $message }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('contact.store') }}" class="space-y-5" x-data="{ submitting: false }" @submit="submitting = true">
                            @csrf

                            <div class="absolute -left-[9999px] top-0 h-0 w-0 overflow-hidden" aria-hidden="true">
                                <label for="contact-website">{{ __('اتركه فارغاً') }}</label>
                                <input type="text" id="contact-website" name="website" tabindex="-1" autocomplete="off" value="">
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <label for="contact-name" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الاسم') }} <span class="text-error">*</span></label>
                                    <input type="text" id="contact-name" name="name" required autocomplete="name" maxlength="120"
                                        value="{{ old('name') }}" placeholder="{{ __('اسمك الكامل') }}"
                                        class="w-full rounded-xl border bg-surface-container-lowest px-4 py-3 text-body-lg outline-none transition-all focus:border-transparent focus:ring-2 focus:ring-primary {{ $errors->has('name') ? 'border-error' : 'border-outline-variant' }}">
                                    @error('name')<p class="text-body-sm text-error">{{ $message }}</p>@enderror
                                </div>

                                <div class="space-y-2">
                                    <label for="contact-email" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('البريد الإلكتروني') }} <span class="text-error">*</span></label>
                                    <input type="email" id="contact-email" name="email" required autocomplete="email" maxlength="190" dir="ltr"
                                        value="{{ old('email') }}" placeholder="name@example.com"
                                        class="w-full rounded-xl border bg-surface-container-lowest px-4 py-3 text-body-lg outline-none transition-all focus:border-transparent focus:ring-2 focus:ring-primary {{ $errors->has('email') ? 'border-error' : 'border-outline-variant' }}">
                                    @error('email')<p class="text-body-sm text-error">{{ $message }}</p>@enderror
                                </div>

                                <div class="space-y-2">
                                    <label for="contact-phone" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('رقم الهاتف') }}</label>
                                    <input type="tel" id="contact-phone" name="phone" autocomplete="tel" maxlength="40" dir="ltr"
                                        value="{{ old('phone') }}" placeholder="+970 59 000 0000"
                                        class="w-full rounded-xl border bg-surface-container-lowest px-4 py-3 text-body-lg outline-none transition-all focus:border-transparent focus:ring-2 focus:ring-primary {{ $errors->has('phone') ? 'border-error' : 'border-outline-variant' }}">
                                    @error('phone')<p class="text-body-sm text-error">{{ $message }}</p>@enderror
                                </div>

                                <div class="space-y-2">
                                    <label for="contact-company" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اسم المشروع') }}</label>
                                    <input type="text" id="contact-company" name="company" autocomplete="organization" maxlength="150"
                                        value="{{ old('company') }}" placeholder="{{ __('اسم مشروعك أو نشاطك') }}"
                                        class="w-full rounded-xl border bg-surface-container-lowest px-4 py-3 text-body-lg outline-none transition-all focus:border-transparent focus:ring-2 focus:ring-primary {{ $errors->has('company') ? 'border-error' : 'border-outline-variant' }}">
                                    @error('company')<p class="text-body-sm text-error">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="contact-subject" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الموضوع') }} <span class="text-error">*</span></label>
                                <select id="contact-subject" name="subject" required
                                    class="w-full rounded-xl border bg-surface-container-lowest px-4 py-3 text-body-lg outline-none transition-all focus:border-transparent focus:ring-2 focus:ring-primary {{ $errors->has('subject') ? 'border-error' : 'border-outline-variant' }}">
                                    @foreach ($subjects as $value => $label)
                                        <option value="{{ $value }}" @selected(old('subject') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('subject')<p class="text-body-sm text-error">{{ $message }}</p>@enderror
                            </div>

                            <input type="hidden" id="contact-plan" name="plan" value="{{ old('plan') }}">

                            <div class="space-y-2">
                                <label for="contact-message" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الرسالة') }} <span class="text-error">*</span></label>
                                <textarea id="contact-message" name="message" required rows="5" minlength="10" maxlength="4000"
                                    placeholder="{{ __('احكِ لنا عن مشروعك وما تحتاجه...') }}"
                                    class="w-full rounded-xl border bg-surface-container-lowest px-4 py-3 text-body-lg outline-none transition-all focus:border-transparent focus:ring-2 focus:ring-primary {{ $errors->has('message') ? 'border-error' : 'border-outline-variant' }}">{{ old('message') }}</textarea>
                                @error('message')<p class="text-body-sm text-error">{{ $message }}</p>@enderror
                            </div>

                            <button type="submit" :disabled="submitting"
                                class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-6 py-4 font-headline-md text-headline-md font-bold text-on-primary shadow-lg shadow-primary/20 transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-[0.98] disabled:opacity-60"
                                title="{{ __('إرسال الرسالة') }}">
                                <span class="material-symbols-outlined rtl:-scale-x-100" :class="submitting && 'animate-spin'" x-text="submitting ? 'progress_activity' : 'send'">send</span>
                                <span x-text="submitting ? @js(__('جارٍ الإرسال...')) : @js(__('إرسال الرسالة'))">{{ __('إرسال الرسالة') }}</span>
                            </button>

                            <p class="text-center font-body-sm text-outline">
                                {{ __('بإرسالك هذا النموذج فإنك توافق على') }}
                                <a href="{{ route('privacy') }}" class="font-bold text-primary hover:underline">{{ __('سياسة الخصوصية') }}</a>.
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-inline-md pb-24 md:px-inline-lg md:pb-32">
            <div class="reveal relative overflow-hidden rounded-[2rem] bg-primary-container px-8 py-16 text-center shadow-2xl sm:py-20 md:px-16 md:py-24">
                <div class="pointer-events-none absolute -top-1/3 start-[-5%] h-72 w-72 rounded-full bg-white/10 blur-3xl" aria-hidden="true"></div>
                <div class="pointer-events-none absolute -bottom-1/2 end-[-5%] h-80 w-80 rounded-full bg-secondary-container/20 blur-3xl" aria-hidden="true"></div>
                <div class="relative">
                    <h2 class="font-display-lg text-3xl font-bold text-white sm:text-4xl md:text-5xl">{{ __('جاهز لتنمية مشروعك؟') }}</h2>
                    <p class="mx-auto mt-4 max-w-xl font-body-lg text-white/80">{{ __('انضم إلى المشاريع التي تدير طلباتها وحساباتها باحترافية. جهّز مساحتك خلال دقائق.') }}</p>
                    <a href="{{ route('signup') }}"
                        class="group mt-8 inline-flex items-center gap-2 rounded-xl bg-white px-8 py-4 font-headline-md text-headline-md font-bold text-[#1e3a8a] shadow-xl transition-all hover:bg-white/90 active:scale-[0.98]" title="{{ __('أنشئ حسابك المجاني') }}">
                        <span>{{ __('أنشئ حسابك المجاني') }}</span>
                        <span class="material-symbols-outlined rtl:-scale-x-100 transition-transform group-hover:translate-x-1 rtl:group-hover:-translate-x-1">arrow_forward</span>
                    </a>
                    <p class="mt-5 font-body-sm text-white/60">{{ __('١٤ يوماً مجاناً - بدون بطاقة ائتمان.') }}</p>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-outline-variant/60 bg-surface-container-lowest">
        <div class="mx-auto max-w-7xl px-inline-md py-16 md:px-inline-lg md:py-20">
            <div class="grid gap-10 md:grid-cols-[1.5fr_1fr_1fr_1fr] md:gap-12">
                <div>
                    <div class="flex items-center gap-3">
                        <x-logo />
                        <span class="font-display-lg text-2xl font-bold text-primary">{{ __('حوالاتي') }}</span>
                    </div>
                    <p class="mt-4 max-w-xs font-body-sm leading-relaxed text-on-surface-variant">
                        {{ __('نظام عربي متكامل لإدارة الطلبات والحسابات والتقارير لأي مشروع - بسرعة وأمان.') }}
                    </p>
                    @if ($supportEmail)
                        <a href="mailto:{{ $supportEmail }}" class="mt-4 inline-flex items-center gap-2 font-body-sm font-bold text-primary hover:underline" dir="ltr">
                            <span class="material-symbols-outlined !text-lg" aria-hidden="true">mail</span>{{ $supportEmail }}
                        </a>
                    @endif
                </div>

                @foreach ([
                    __('المنتج') => [[__('ما هو حوالاتي'), '#tour'], [__('المزايا'), '#features'], [__('كيف يعمل'), '#how'], [__('الأسعار'), '#pricing']],
                    __('الشركة') => [[__('الأمان'), route('security')], [__('سياسة الخصوصية'), route('privacy')], [__('شروط الاستخدام'), route('terms')], [__('تواصل معنا'), '#contact']],
                    __('الحساب') => [[__('تسجيل الدخول'), route('login')], [__('إنشاء حساب'), route('signup')], [__('مركز المساعدة'), route('help.index')], [__('الأسئلة المتكرّرة'), '#faq']],
                ] as $group => $links)
                    <div>
                        <h2 class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ $group }}</h2>
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
            var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            var revealables = document.querySelectorAll('.reveal:not(.is-visible)');
            if (!('IntersectionObserver' in window)) {
                revealables.forEach(function (el) { el.classList.add('is-visible'); });
            } else {
                var io = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (!entry.isIntersecting) return;
                        entry.target.classList.add('is-visible');
                        io.unobserve(entry.target);
                    });
                }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
                revealables.forEach(function (el) { io.observe(el); });
            }

            var counters = document.querySelectorAll('.count');
            if ('IntersectionObserver' in window && !reduced) {
                var countIo = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (!entry.isIntersecting) return;
                        countIo.unobserve(entry.target);

                        var el = entry.target;
                        var target = parseInt(el.dataset.count, 10);
                        if (isNaN(target)) return;

                        var started = null;
                        var duration = 1100;
                        var step = function (now) {
                            if (started === null) started = now;
                            var p = Math.min((now - started) / duration, 1);

                            el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
                            if (p < 1) requestAnimationFrame(step);
                        };
                        requestAnimationFrame(step);
                    });
                }, { threshold: 0.6 });
                counters.forEach(function (el) { countIo.observe(el); });
            }

            var bar = document.getElementById('scroll-progress');
            if (bar) {
                var ticking = false;
                var paint = function () {
                    var max = document.documentElement.scrollHeight - window.innerHeight;
                    bar.style.width = (max > 0 ? (window.scrollY / max) * 100 : 0) + '%';
                    ticking = false;
                };
                window.addEventListener('scroll', function () {

                    if (!ticking) { ticking = true; requestAnimationFrame(paint); }
                }, { passive: true });
                paint();
            }

            var planField = document.getElementById('contact-plan');
            document.querySelectorAll('.plan-cta').forEach(function (cta) {
                cta.addEventListener('click', function () {
                    if (planField) planField.value = cta.dataset.plan || '';
                    var subject = document.getElementById('contact-subject');
                    if (subject) subject.value = 'sales';
                });
            });
        });
</script>

    <div x-show="tipText !== ''" x-cloak
        class="pointer-events-none fixed z-[110] whitespace-nowrap rounded-lg bg-inverse-surface px-3 py-1.5 font-body-sm text-body-sm font-bold text-inverse-on-surface shadow-xl"
        :style="`top:${tipTop}px; left:${tipLeft}px; transform:translateX(-50%);`"
        x-text="tipText" role="tooltip" style="display:none"></div>
</body>
</html>
