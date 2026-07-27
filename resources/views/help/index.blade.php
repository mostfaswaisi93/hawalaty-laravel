{{--
    One route serves both audiences: this page carries no authenticated data, and
    the guest footer links it, so it must render for signed-out visitors too. The
    layout follows the session - the app shell (with its sidebar) for a signed-in
    tenant, the guest shell otherwise.
--}}
@extends(auth('tenant')->check() ? 'layouts.app' : 'layouts.guest')

@section('title', __('مركز المساعدة - حوالاتي'))

@section('content')
    {{-- lg:col-span-2 is required under layouts.guest, whose <main> is a
         two-column grid; it is inert inside layouts.app. --}}
    <div class="page-enter mx-auto w-full max-w-4xl space-y-8 px-inline-md py-8 md:px-inline-lg lg:col-span-2">
        <header class="text-center">
            <div class="reveal-scale mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-3xl bg-primary/10 text-primary" style="--d:120ms">
                <span class="material-symbols-outlined !text-4xl">help</span>
            </div>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('كيف يمكننا مساعدتك؟') }}</h1>
            <p class="mt-2 font-body-lg text-on-surface-variant">{{ __('دليل سريع وأجوبة لأكثر الأسئلة شيوعاً.') }}</p>
        </header>

        {{-- quick links --}}
        <section>
            <h2 class="mb-4 font-label-caps text-label-caps uppercase tracking-wider text-secondary">{{ __('روابط سريعة') }}</h2>
            <div class="stagger grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['/transfers', 'add_shopping_cart', __('طلب جديد'), __('أنشئ طلباً واحسب المجموع تلقائياً')],
                    ['/history', 'receipt_long', __('السجل'), __('تصفّح وابحث في كل طلباتك')],
                    ['/banks', 'account_balance', __('البنوك والمحافظ'), __('أدر وسائل الدفع')],
                    ['/menu', 'restaurant_menu', __('قائمة الطعام'), __('نظّم الفئات والأصناف')],
                    ['/reports', 'assessment', __('التقارير'), __('تابع إيراداتك ونشاطك')],
                    ['/team', 'group', __('الفريق'), __('ادعُ موظفين وامنح صلاحيات')],
                ] as [$href, $icon, $title, $desc])
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

        {{-- FAQ --}}
        <section x-data="{ open: 0 }">
            <h2 class="mb-4 font-label-caps text-label-caps uppercase tracking-wider text-secondary">{{ __('الأسئلة الشائعة') }}</h2>
            <div class="stagger space-y-3">
                @foreach ([
                    [__('كيف أنشئ طلباً جديداً؟'), __('من «طلب جديد»، اختر الأصناف من القائمة لتُضاف إلى السلة، حدّد اسم العميل وطريقة الدفع (نقدي أو تحويل بنكي)، ثم اضغط «إنشاء الطلب». يُحسب الإجمالي تلقائياً من الأصناف.')],
                    [__('كيف أضيف بنكاً أو محفظة؟'), __('من «البنوك والمحافظ» اضغط «إضافة»، أدخل الاسم والنوع (بنك/محفظة) والرمز واختيارياً الهاتف. يمكنك تفعيل أو إيقاف أي وسيلة دفع في أي وقت من مفتاح الحالة.')],
                    [__('كيف أنظّم قائمة الطعام؟'), __('من «قائمة الطعام» أنشئ فئة أولاً (مثل: المشاوي)، ثم أضِف الأصناف إليها مع أسعارها. يمكنك إخفاء أي صنف مؤقتاً بإيقاف «متاح للطلب» دون حذفه.')],
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

        {{-- contact --}}
        <section class="reveal-up tonal-elevation flex flex-col items-center gap-4 rounded-3xl border border-outline-variant/50 bg-primary/5 p-8 text-center" style="--d:200ms">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-secondary-container/15 text-secondary">
                <span class="material-symbols-outlined !text-3xl">support_agent</span>
            </div>
            <div>
                <h3 class="font-headline-md text-headline-md text-on-surface">{{ __('ما زلت بحاجة إلى مساعدة؟') }}</h3>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('فريق الدعم جاهز لخدمتك.') }}</p>
            </div>
            <div class="flex flex-wrap items-center justify-center gap-3">
                <a href="mailto:{{  }}" class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95" title="{{ __('راسلنا') }}">
                    <span class="material-symbols-outlined !text-xl">mail</span>
                    {{ __('راسلنا') }}
                </a>
                <span class="font-body-sm text-on-surface-variant" dir="ltr">+970 59 000 0000</span>
            </div>
        </section>
    </div>
@endsection
