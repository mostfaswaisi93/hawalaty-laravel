@extends('layouts.guest')

@section('title', __('الأمان وحماية البيانات - حوالاتي'))

@php
    // Read by layouts.guest through get_defined_vars() - see the <x-seo> call there.
    $metaDescription = __('كيف نحمي بيانات مشروعك في حوالاتي: قاعدة بيانات مستقلة لكل حساب، تشفير النقل، صلاحيات دقيقة، سجل نشاط غير قابل للتعديل، ونسخ احتياطية دورية.');
    $metaRobots = 'index, follow, max-image-preview:large, max-snippet:-1';
    $seoSchemas = [[
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => __('الأمان وحماية البيانات'),
        'description' => $metaDescription,
        'url' => url()->current(),
        'inLanguage' => app()->getLocale(),
        'isPartOf' => ['@type' => 'WebSite', 'name' => __('حوالاتي'), 'url' => route('home')],
    ]];
@endphp

@section('content')
    @php
        // The claims on this page are the ones the codebase can actually back
        // up. Nothing here describes a certification the platform does not
        // hold - see the closing note.
        $pillars = [
            [
                'dns',
                __('قاعدة بيانات مستقلة لكل حساب'),
                __('العزل هنا فعلي وليس مجرد عمود يفصل الصفوف: لكل مشروع قاعدة بياناته الخاصة، ولا تلتقي بياناتك ببيانات عميل آخر داخل جدول واحد.'),
                [
                    __('الطلبات والحسابات والعملاء والمصروفات وسجل النشاط كلها داخل قاعدة بيانات مشروعك وحده.'),
                    __('قاعدة البيانات المركزية لا تحمل إلا ما يلزم لتشغيل الاشتراك: الحساب والباقة والترخيص والمدفوعات.'),
                    __('خطأ برمجي في استعلام واحد لا يمكن أن يُسرّب بيانات مشروع إلى آخر، لأن الاتصال نفسه مختلف.'),
                ],
            ],
            [
                'lock',
                __('التشفير وحماية الحسابات'),
                __('كلمات المرور والجلسات والاتصال بالخادم تُعامل على أنها الحلقة الأضعف، لأنها كذلك عادةً.'),
                [
                    __('كلمات المرور تُحفظ مُجزَّأة بخوارزمية bcrypt، ولا تُخزَّن كنص أبداً ولا يمكن استرجاعها - حتى من قِبلنا.'),
                    __('كل الاتصالات تمرّ عبر HTTPS، وملفات الجلسة تُوقَّع وتُشفَّر قبل أن تصل إلى المتصفح.'),
                    __('كل نموذج يحمل رمز CSRF، ومحاولات تسجيل الدخول محدودة المعدل لإيقاف تخمين كلمات المرور.'),
                    __('واجهة الـ API تعمل برموز وصول (tokens) قابلة للإلغاء في أي لحظة من صفحة الأجهزة.'),
                ],
            ],
            [
                'admin_panel_settings',
                __('صلاحيات دقيقة لكل إجراء'),
                __('الموظف الذي يستقبل الطلبات لا يحتاج أن يرى الأرباح، والمحاسب لا يحتاج أن يعدّل الأسعار. الأدوار هنا تعني ذلك حرفياً.'),
                [
                    __('أدوار جاهزة (مالك، مدير، موظف) وصلاحيات مستقلة لكل إجراء يمكن تعديلها كما يناسب فريقك.'),
                    __('كل صلاحية تُفحص على الخادم، لا في المتصفح فقط - إخفاء زر ليس حماية.'),
                    __('دعوة عضو أو سحب صلاحيته يسري أثره فوراً على جلسته المفتوحة.'),
                ],
            ],
            [
                'fact_check',
                __('سجل نشاط غير قابل للتعديل'),
                __('كل إجراء مؤثر يُكتب في سجل لا يوفّر التطبيق أي طريقة لتعديله أو حذفه، لأن قيمة السجل كلها في كونه غير قابل للتغيير.'),
                [
                    __('من فعل ماذا ومتى ومن أي جهاز - لكل طلب ودفعة ومصروف وتغيير صلاحية.'),
                    __('السجلات المالية تُحذف حذفاً ناعماً: تختفي من قوائمك وتبقى للتدقيق وسلامة التقارير.'),
                    __('يمكنك مراجعة السجل بنفسك في أي وقت من داخل حسابك.'),
                ],
            ],
            [
                'backup',
                __('نسخ احتياطية دورية'),
                __('النسخة الاحتياطية التي لم تُختبر ليست نسخة احتياطية. نأخذها بشكل دوري ونحتفظ بها منفصلة عن قاعدة البيانات العاملة.'),
                [
                    __('نسخ دورية لقاعدة بيانات كل حساب، قابلة للاسترجاع عند الحاجة.'),
                    __('يمكن طلب نسخة من بياناتك في أي وقت، وتصدير جداولك بصيغة CSV مباشرة من التطبيق.'),
                    __('عند إنهاء الاشتراك نهائياً يمكنك طلب حذف قاعدة بيانات حسابك بالكامل.'),
                ],
            ],
            [
                'cloud_off',
                __('يعمل حتى بدون إنترنت'),
                __('انقطاع الشبكة في منتصف يوم عمل لا يجوز أن يوقف المشروع، ولا أن يضيّع طلباً سُجّل فعلاً.'),
                [
                    __('الطلبات التي تُنشئها بدون اتصال تُحفظ محلياً وتُرسَل تلقائياً فور عودة الشبكة.'),
                    __('الصفحات التي فتحتها من قبل تبقى قابلة للقراءة أثناء الانقطاع.'),
                    __('لا يُعرض أبداً تأكيد نجاح لطلب لم يصل إلى الخادم فعلياً.'),
                ],
            ],
        ];

        $ownership = [
            ['inventory_2', __('بياناتك ملكك'), __('ما تُدخله في حوالاتي يخصّ مشروعك أنت. لا نبيعه ولا نؤجّره ولا نستخدمه في الإعلانات ولا في تدريب نماذج ذكاء اصطناعي.')],
            ['download', __('التصدير في أي وقت'), __('تنزيل بياناتك بصيغة CSV، والتقارير والإيصالات بصيغة PDF، دون الحاجة إلى مراسلة أحد.')],
            ['policy', __('إفصاح قانوني فقط'), __('لا نفصح عن بياناتك إلا استجابةً لطلب قانوني صحيح ومُلزِم، ونُعلمك بذلك ما لم يمنعنا القانون.')],
        ];
    @endphp

    <section class="page-enter mx-auto w-full max-w-4xl px-inline-md py-16 md:px-inline-lg lg:col-span-2">
        <header class="reveal-up text-center" style="--d:80ms">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-3xl bg-primary/10 text-primary">
                <span class="material-symbols-outlined !text-4xl" aria-hidden="true">shield_person</span>
            </div>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الأمان وحماية البيانات') }}</h1>
            <p class="mx-auto mt-3 max-w-2xl font-body-lg leading-relaxed text-on-surface-variant">
                {{ __('مشروعك يضع أرقامه وعملاءه عندنا. هذه الصفحة تشرح بالضبط كيف نحميها - بما نفعله فعلاً، لا بما يبدو جميلاً في صفحة تسويقية.') }}
            </p>
        </header>

        {{-- headline numbers --}}
        <div class="stagger mt-10 grid grid-cols-2 gap-4 md:grid-cols-4">
            @foreach ([
                ['database', __('قاعدة لكل حساب'), __('عزل فعلي')],
                ['lock', __('HTTPS + bcrypt'), __('نقل وتخزين')],
                ['history', __('سجل دائم'), __('غير قابل للتعديل')],
                ['backup', __('نسخ دورية'), __('قابلة للاسترجاع')],
            ] as $i => [$icon, $big, $small])
                <div class="reveal-up tonal-elevation flex flex-col items-center gap-2 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest px-3 py-5 text-center" style="--d: {{ 120 + $i * 70 }}ms">
                    <span class="material-symbols-outlined !text-3xl text-primary" aria-hidden="true">{{ $icon }}</span>
                    <span class="font-headline-md text-body-lg font-bold text-on-surface">{{ $big }}</span>
                    <span class="font-body-sm text-outline">{{ $small }}</span>
                </div>
            @endforeach
        </div>

        <div class="stagger mt-6 space-y-5">
            @foreach ($pillars as [$icon, $heading, $intro, $points])
                <article class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                            <span class="material-symbols-outlined" aria-hidden="true">{{ $icon }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 class="font-headline-md text-headline-md text-on-surface">{{ $heading }}</h2>
                            <p class="mt-2 font-body-lg leading-relaxed text-on-surface-variant">{{ $intro }}</p>

                            <ul class="mt-4 space-y-2.5 border-t border-outline-variant/50 pt-4">
                                @foreach ($points as $point)
                                    <li class="flex items-start gap-2.5 text-start font-body-sm leading-relaxed text-on-surface-variant">
                                        <span class="material-symbols-outlined mt-0.5 flex-shrink-0 !text-base text-primary" data-weight="fill" aria-hidden="true">check_circle</span>
                                        <span>{{ $point }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </article>
            @endforeach

            {{-- ownership --}}
            <article class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-low p-5 md:p-6">
                <h2 class="font-headline-md text-headline-md text-on-surface">{{ __('ملكية البيانات') }}</h2>
                <div class="mt-5 grid gap-5 md:grid-cols-3">
                    @foreach ($ownership as [$icon, $title, $body])
                        <div>
                            <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-secondary-container/15 text-secondary">
                                <span class="material-symbols-outlined !text-xl" aria-hidden="true">{{ $icon }}</span>
                            </div>
                            <h3 class="mt-3 font-headline-md text-body-lg font-bold text-on-surface">{{ $title }}</h3>
                            <p class="mt-1.5 font-body-sm leading-relaxed text-on-surface-variant">{{ $body }}</p>
                        </div>
                    @endforeach
                </div>
            </article>

            {{-- report a vulnerability --}}
            <article class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-primary/5 p-5 md:p-6">
                <div class="flex flex-col items-start gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-start gap-4">
                        <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-secondary-container/15 text-secondary">
                            <span class="material-symbols-outlined" aria-hidden="true">bug_report</span>
                        </div>
                        <div class="min-w-0">
                            <h2 class="font-headline-md text-headline-md text-on-surface">{{ __('وجدت ثغرة؟ أخبرنا') }}</h2>
                            <p class="mt-2 font-body-lg leading-relaxed text-on-surface-variant">{{ __('نتعامل مع كل بلاغ أمني بجدية ونردّ عليه. أرسل لنا وصفاً للمشكلة وخطوات إعادة إنتاجها، ولا تنشرها علناً قبل أن نتمكّن من معالجتها.') }}</p>
                        </div>
                    </div>
                    <a href="mailto:{{ $supportEmail }}"
                        class="flex flex-shrink-0 items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95"
                        title="{{ __('راسلنا') }}">
                        <span class="material-symbols-outlined !text-xl" aria-hidden="true">mail</span>
                        <span dir="ltr">{{ $supportEmail }}</span>
                    </a>
                </div>
            </article>

            {{-- honesty note: the same shape as the legal-review note on privacy/terms --}}
            <aside class="rounded-3xl border border-dashed border-tertiary/50 bg-tertiary-container/15 p-5 md:p-6" role="note">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-tertiary/10 text-tertiary">
                        <span class="material-symbols-outlined" aria-hidden="true">info</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-headline-md text-headline-md text-on-surface">{{ __('ما لا ندّعيه') }}</h2>
                        <p class="mt-2 font-body-sm leading-relaxed text-on-surface-variant">{{ __('لا نحمل حتى الآن شهادات تدقيق خارجية مثل SOC 2 أو ISO 27001، ولا ندّعي ذلك. كل ما ورد أعلاه يصف كيف بُني النظام فعلياً، ويمكنك سؤالنا عن أي بند منه بالتفصيل قبل أن تقرّر.') }}</p>
                    </div>
                </div>
            </aside>

            <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                <a href="{{ route('privacy') }}" class="flex items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container" title="{{ __('سياسة الخصوصية') }}">
                    <span class="material-symbols-outlined !text-xl text-primary" aria-hidden="true">privacy_tip</span>{{ __('سياسة الخصوصية') }}
                </a>
                <a href="{{ route('terms') }}" class="flex items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container" title="{{ __('شروط الاستخدام') }}">
                    <span class="material-symbols-outlined !text-xl text-primary" aria-hidden="true">gavel</span>{{ __('شروط الاستخدام') }}
                </a>
            </div>
        </div>
    </section>
@endsection
