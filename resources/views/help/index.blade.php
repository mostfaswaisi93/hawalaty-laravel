@extends(auth('tenant')->check() ? 'layouts.app' : 'layouts.guest')

@section('title', __('مركز المساعدة - حوالاتي'))

@php

    $catalogNaming = app(\App\Services\Tenant\CatalogNaming::class);
@endphp

@php

    $metaDescription = __('أجوبة سريعة عن استخدام حوالاتي: إنشاء الطلبات، الحسابات والمحافظ، الفريق والصلاحيات، التقارير، والعمل بدون إنترنت.');
    $metaRobots = 'index, follow';
@endphp

@section('content')
    @php $inAppShell = auth('tenant')->check(); @endphp

    <div @class([
        'page-enter w-full space-y-8',
        'mx-auto max-w-4xl px-inline-md py-8 md:px-inline-lg lg:col-span-2' => ! $inAppShell,
    ])>
        <header class="text-center">
            <div class="reveal-scale mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-3xl bg-primary/10 text-primary" style="--d:120ms">
                <span class="material-symbols-outlined !text-4xl">help</span>
            </div>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('كيف يمكننا مساعدتك؟') }}</h1>
            <p class="mt-2 font-body-lg text-on-surface-variant">{{ __('دليل سريع وأجوبة لأكثر الأسئلة شيوعاً.') }}</p>
        </header>

        <div class="grid items-start gap-6 xl:grid-cols-3">

        <section class="xl:order-2 xl:col-span-1">
            <h2 class="mb-4 font-label-caps text-label-caps uppercase tracking-wider text-secondary">{{ __('روابط سريعة') }}</h2>
            <div class="stagger grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                @php
                    /*
                     * Filtered the same way the sidebar is: a link the reader
                     * cannot open is worse than no link, because it looks like the
                     * page is broken rather than like the feature is not theirs.
                     * `permission`/`feature` null means always shown - which is
                     * what a signed-out visitor gets.
                     */
                    $member = auth('tenant')->user();
                    $license = app()->bound('currentLicense') ? app('currentLicense') : null;

                    $quickLinks = array_filter([
                        ['/transfers', 'add_shopping_cart', __('طلب جديد'), __('أنشئ طلباً واحسب المجموع تلقائياً'), \App\Enums\PermissionKey::TransfersCreate, null, true],
                        ['/history', 'receipt_long', __('السجل'), __('تصفّح وابحث في كل طلباتك'), null, null, true],
                        ['/accounts', 'wallet', __('الحسابات'), __('أدر وسائل الدفع'), \App\Enums\PermissionKey::BanksManage, null, true],
                        ['/menu', $catalogNaming->icon(), $catalogNaming->catalog(), __('نظّم الفئات والأصناف'), \App\Enums\PermissionKey::MenuManage, \App\Enums\PackageFeature::Menu, $catalogNaming->isConfigured()],
                        ['/reports', 'assessment', __('التقارير'), __('تابع إيراداتك ونشاطك'), null, null, true],
                        ['/team', 'group', __('الفريق'), __('ادعُ موظفين وامنح صلاحيات'), \App\Enums\PermissionKey::TeamManage, \App\Enums\PackageFeature::Team, true],
                    ], fn ($l) => $l[6]
                        && (! $l[4] || ! $member || $member->hasPermission($l[4]))
                        && (! $l[5] || ! $license || $license->hasFeature($l[5])));
                @endphp
                @foreach ($quickLinks as [$href, $icon, $title, $desc])
                    <a href="{{ $href }}" class="tonal-elevation press group flex items-start gap-4 rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-5 transition-all hover:border-primary">
                        <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-on-primary">
                            <span class="material-symbols-outlined">{{ $icon }}</span>
                        </div>
                        <div>
                            <p class="font-body-lg font-bold text-on-surface">{{ $title }}</p>
                            <p class="mt-0.5 font-body-sm text-on-surface-variant">{{ $desc }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        <section x-data="{ open: 0 }" class="xl:order-1 xl:col-span-2">
            <h2 class="mb-4 font-label-caps text-label-caps uppercase tracking-wider text-secondary">{{ __('الأسئلة الشائعة') }}</h2>
            <div class="stagger space-y-3">
                @foreach ([
                    [__('كيف أنشئ طلباً جديداً؟'), __('من «طلب جديد»، اختر الأصناف من القائمة لتُضاف إلى السلة، حدّد اسم العميل وطريقة الدفع (نقدي أو تحويل بنكي)، ثم اضغط «إنشاء الطلب». يُحسب الإجمالي تلقائياً من الأصناف.')],
                    [__('كيف أضيف حساباً بنكياً أو محفظة؟'), __('من «الحسابات» اضغط «إضافة»، أدخل صاحب الحساب والنوع (بنك/محفظة) والعملة واختيارياً رقم واتساب للإشعارات. يمكنك تفعيل أو إيقاف أي حساب في أي وقت من مفتاح الحالة.')],
                    [__('كيف أنظّم :catalog؟', ['catalog' => $catalogNaming->catalog()]), __('من «:catalog» أنشئ :category أولاً، ثم أضِف :items إليها مع أسعارها. يمكنك إخفاء أي :item مؤقتاً بإيقاف «متاح للطلب» دون حذفه.', ['catalog' => $catalogNaming->catalog(), 'category' => $catalogNaming->category(), 'items' => __('الأصناف'), 'item' => $catalogNaming->item()])],
                    [__('ما الفرق بين الأدوار؟'), __('المالك يملك كل الصلاحيات. المدير يملك كل الصلاحيات عدا إدارة الفريق. الموظف يمكنه إنشاء الطلبات فقط. يمكنك تعيين الدور المناسب لكل عضو من صفحة «الفريق».')],
                    [__('ماذا تعني حالات الطلب؟'), __('«مكتملة» أي تم استلام الدفع وإنهاء الطلب، «قيد الانتظار» أي جديد لم يُنجَز بعد، و«فاشلة» أي أُلغي أو تعذّر إتمامه.')],
                    [__('كيف أتابع إيراداتي؟'), __('لوحة التحكم تعرض إجمالي الإيرادات ونشاط الأسبوع وأحدث الطلبات. وصفحة «التقارير» تعطيك تفصيلاً أعمق لأداء عملك.')],
                ] as $i => [$q, $a])
                    <div class="tonal-elevation overflow-hidden rounded-2xl border border-outline-variant/50 bg-surface-container-lowest">
                        <button type="button" @click="open === {{ $i }} ? open = null : open = {{ $i }}"
                            id="faq-question-{{ $i }}" aria-controls="faq-answer-{{ $i }}" :aria-expanded="open === {{ $i }} ? 'true' : 'false'"
                            class="flex w-full items-center justify-between gap-4 p-5 text-start transition-colors hover:bg-surface-container-low/50">
                            <span class="font-body-lg font-bold text-on-surface">{{ $q }}</span>
                            <span class="material-symbols-outlined flex-shrink-0 text-primary transition-transform" :class="open === {{ $i }} && 'rotate-180'">expand_more</span>
                        </button>
                        <div x-show="open === {{ $i }}" x-cloak id="faq-answer-{{ $i }}" role="region" aria-labelledby="faq-question-{{ $i }}"
                            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                            <p class="border-t border-outline-variant/50 p-5 font-body-lg leading-relaxed text-on-surface-variant">{{ $a }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="reveal-up tonal-elevation flex flex-col items-center gap-4 rounded-3xl border border-outline-variant/50 bg-primary/5 p-8 text-center xl:order-3 xl:col-span-1" style="--d:200ms">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-secondary-container/15 text-secondary">
                <span class="material-symbols-outlined !text-3xl">support_agent</span>
            </div>
            <div>
                <h3 class="font-headline-md text-headline-md text-on-surface">{{ __('ما زلت بحاجة إلى مساعدة؟') }}</h3>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('فريق الدعم جاهز لخدمتك.') }}</p>
            </div>
            <div class="flex flex-wrap items-center justify-center gap-3">
                <a href="mailto:{{ $supportEmail }}" class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95" title="{{ $supportEmail }}">
                    <span class="material-symbols-outlined !text-xl">mail</span>
                    {{ __('راسلنا') }}
                </a>
                {{-- The operator's own number from /admin/settings, not a constant -
                     it was hardcoded here while `support_phone` was already being
                     shared with this view. --}}
                @if ($supportPhone)
                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $supportPhone) }}"
                        class="flex items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary">
                        <span class="material-symbols-outlined !text-xl">call</span>
                        <span dir="ltr">{{ $supportPhone }}</span>
                    </a>
                @endif
            </div>
        </section>

        </div></div>
@endsection
