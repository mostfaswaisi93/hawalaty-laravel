@extends('layouts.guest')

@section('title', __('شروط الخدمة - حوالاتي'))

@section('content')
    <section class="page-enter mx-auto w-full max-w-4xl px-inline-md py-16 md:px-inline-lg lg:col-span-2">

        {{-- ── header ─────────────────────────────────────────────────────── --}}
        <header class="text-center">
            <div class="reveal-scale mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-3xl bg-primary/10 text-primary" style="--d:120ms">
                <span class="material-symbols-outlined !text-4xl" aria-hidden="true">contract</span>
            </div>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('شروط الخدمة') }}</h1>
            <p class="mx-auto mt-3 max-w-2xl font-body-lg leading-relaxed text-on-surface-variant">
                {{ __('هذه الشروط تشرح كيف تعمل منصّة حوالاتي، وما الذي نلتزم به تجاهك، وما الذي نتوقّعه منك أثناء استخدامك لمساحة عمل نشاطك التجاري.') }}
            </p>
            <p class="mt-5 inline-flex items-center gap-2 rounded-full border border-outline-variant/60 bg-surface-container-low px-4 py-1.5 font-body-sm text-on-surface-variant">
                <span class="material-symbols-outlined !text-base" aria-hidden="true">event</span>
                {{ __('آخر تحديث: يوليو ٢٠٢٦') }}
            </p>
        </header>

        {{-- ── table of contents ──────────────────────────────────────────── --}}
        <nav class="tonal-elevation mt-10 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6"
            aria-labelledby="toc-heading">
            <h2 id="toc-heading" class="font-label-caps text-label-caps uppercase tracking-wider text-secondary">
                {{ __('محتويات الصفحة') }}
            </h2>
            <ul class="mt-4 grid gap-x-6 gap-y-2 sm:grid-cols-2">
                @foreach ([
                    ['acceptance', __('قبول الشروط ومن يحقّ له الاستخدام')],
                    ['accounts', __('الحسابات وبيانات الدخول')],
                    ['subscriptions', __('الاشتراكات والتراخيص')],
                    ['acceptable-use', __('الاستخدام المقبول')],
                    ['your-data', __('بيانات عملك ومحتواك')],
                    ['availability', __('التوافر والصيانة والنسخ الاحتياطية')],
                    ['termination', __('الإيقاف وإنهاء الخدمة')],
                    ['liability', __('حدود المسؤولية وإخلاء الضمانات')],
                    ['changes', __('تعديل هذه الشروط')],
                ] as [$anchor, $label])
                    <li>
                        <a href="#{{ $anchor }}"
                            class="group flex items-center gap-2 rounded-lg py-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary"
                            title="{{ $label }}">
                            <span class="material-symbols-outlined !text-base text-outline transition-colors group-hover:text-primary rtl:-scale-x-100" aria-hidden="true">arrow_forward</span>
                            <span>{{ $label }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        {{-- ── sections ───────────────────────────────────────────────────── --}}
        <div class="stagger mt-6 space-y-6">

            {{-- 1 · acceptance --}}
            <section id="acceptance" class="tonal-elevation scroll-mt-24 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <span class="material-symbols-outlined" aria-hidden="true">handshake</span>
                    </div>
                    <h2 class="mt-1.5 font-headline-md text-headline-md text-on-surface">{{ __('قبول الشروط ومن يحقّ له الاستخدام') }}</h2>
                </div>
                <ul class="mt-4 space-y-3">
                    @foreach ([
                        __('تنظّم هذه الشروط استخدامك لمنصّة حوالاتي («الخدمة»)، وتشمل لوحة التحكم على الويب وواجهة البرمجة (API) التي تستخدمها تطبيقات العميل.'),
                        __('بإنشائك حساباً أو باستخدامك الخدمة، فإنك توافق على هذه الشروط. إن كنت لا توافق عليها، فلا تستخدم الخدمة.'),
                        __('الخدمة مُعدّة للمنشآت التجارية - المطاعم والأعمال الصغيرة - ولمن يعملون داخلها، لا للاستخدام الشخصي غير التجاري.'),
                        __('يجب أن تكون مؤهّلاً قانونياً للتعاقد. وإذا سجّلت نيابة عن منشأة، فأنت تقرّ بأنك مخوّل بإلزامها بهذه الشروط.'),
                    ] as $item)
                        <li class="flex gap-3">
                            <span class="mt-2.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary/40" aria-hidden="true"></span>
                            <p class="font-body-lg leading-relaxed text-on-surface-variant">{{ $item }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- 2 · accounts --}}
            <section id="accounts" class="tonal-elevation scroll-mt-24 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <span class="material-symbols-outlined" aria-hidden="true">key</span>
                    </div>
                    <h2 class="mt-1.5 font-headline-md text-headline-md text-on-surface">{{ __('الحسابات وبيانات الدخول') }}</h2>
                </div>
                <ul class="mt-4 space-y-3">
                    @foreach ([
                        __('تدخل إلى مساحة عملك برمز الترخيص الخاص بمنشأتك، إضافةً إلى بريدك الإلكتروني وكلمة المرور. رمز الترخيص هو ما يحدّد قاعدة البيانات المعزولة التي تعمل عليها منشأتك، فتعامل معه كمعلومة خاصة ولا تنشره.'),
                        __('حساب المالك هو الحساب المسؤول عن مساحة العمل: يدعو أعضاء الفريق، ويمنحهم دور المدير أو الموظف، ويعدّل صلاحياتهم أو يوقفها في أي وقت.'),
                        __('المالك مسؤول عن كل ما يجري داخل مساحة عمله، بما في ذلك تصرّفات أعضاء فريقه، وعن مراجعة الصلاحيات الممنوحة وسحبها فور انتهاء علاقة العمل مع أي عضو.'),
                        __('تلتزم بتقديم بيانات تسجيل صحيحة ومحدَّثة - اسم النشاط واسم المالك والبريد الإلكتروني ورقم الهاتف - وبتحديثها عند تغيّرها، لأننا نعتمد عليها في التواصل معك بشأن حسابك.'),
                        __('أنت مسؤول عن سرية كلمة مرورك وعن الأجهزة المرتبطة بحسابك. أبلغنا فوراً عند اشتباهك بأي دخول غير مصرّح به.'),
                        __('يوثّق سجلّ النشاط داخل مساحة عملك العمليات المهمّة التي تجري عليها، ويمكن مراجعته من داخل الخدمة.'),
                    ] as $item)
                        <li class="flex gap-3">
                            <span class="mt-2.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary/40" aria-hidden="true"></span>
                            <p class="font-body-lg leading-relaxed text-on-surface-variant">{{ $item }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- 3 · subscriptions --}}
            <section id="subscriptions" class="tonal-elevation scroll-mt-24 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <span class="material-symbols-outlined" aria-hidden="true">workspace_premium</span>
                    </div>
                    <h2 class="mt-1.5 font-headline-md text-headline-md text-on-surface">{{ __('الاشتراكات والتراخيص') }}</h2>
                </div>
                <ul class="mt-4 space-y-3">
                    @foreach ([
                        __('الوصول إلى الخدمة مرتبط بترخيص فعّال. يحدّد الترخيص الباقة المشترك بها وما تتيحه من حدود وميزات، مثل عدد الأجهزة التي يمكن ربطها بمساحة عملك.'),
                        __('تبدأ الحسابات الجديدة بفترة تجريبية مجانية بالمدة المعلنة عند التسجيل، ودون الحاجة إلى بيانات بطاقة للبدء.'),
                        __('بعد انتهاء الفترة التجريبية يلزم اشتراك مدفوع لمتابعة الاستخدام. تُسجَّل دفعات الاشتراك على مستوى المنصّة، وتُمدَّد صلاحية الترخيص عند تأكيد الدفعة.'),
                        __('لا يتم التجديد بخصم تلقائي من بطاقتك؛ يبقى تجديد الترخيص خطوةً تتم بتأكيد الدفع.'),
                        __('إذا انتهت صلاحية الترخيص أو جرى تعليقه، يتوقّف الدخول إلى مساحة العمل حتى إعادة تفعيله. بياناتك تبقى محفوظة خلال هذه الفترة ولا تُحذف لمجرّد انتهاء الصلاحية.'),
                        __('يمكنك ترقية باقتك أو تخفيضها، وتسري الحدود الجديدة على مساحة عملك فور تطبيق التغيير.'),
                        __('قد تتغيّر الأسعار المعلنة مستقبلاً. أي تغيير في السعر يُبلَّغ لك قبل سريانه على تجديدك التالي.'),
                    ] as $item)
                        <li class="flex gap-3">
                            <span class="mt-2.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary/40" aria-hidden="true"></span>
                            <p class="font-body-lg leading-relaxed text-on-surface-variant">{{ $item }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- 4 · acceptable use --}}
            <section id="acceptable-use" class="tonal-elevation scroll-mt-24 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <span class="material-symbols-outlined" aria-hidden="true">policy</span>
                    </div>
                    <h2 class="mt-1.5 font-headline-md text-headline-md text-on-surface">{{ __('الاستخدام المقبول') }}</h2>
                </div>
                <p class="mt-4 font-body-lg leading-relaxed text-on-surface-variant">{{ __('عند استخدامك الخدمة تلتزم بما يلي:') }}</p>
                <ul class="mt-4 space-y-3">
                    @foreach ([
                        __('ألّا تستخدم الخدمة في أي نشاط مخالف للقانون، ولا لتسجيل معاملات وهمية أو مضلِّلة.'),
                        __('ألّا تحاول الوصول إلى بيانات منشأة أخرى أو إلى أي حساب لا تملك صلاحية عليه. كل مساحة عمل معزولة في قاعدة بيانات خاصة بها، وأي محاولة لتجاوز هذا العزل تُعدّ مخالفة جسيمة.'),
                        __('ألّا تحاول تعطيل الخدمة أو اختبار حمايتها أو تجاوز آليات تسجيل الدخول وتحديد الصلاحيات.'),
                        __('ألّا تسيء استخدام واجهة البرمجة (API): لا طلبات آلية مفرطة، ولا مشاركة رموز الوصول مع أطراف خارجية، ولا استخدام المزامنة لإغراق الخدمة ببيانات غير حقيقية.'),
                        __('ألّا تعيد بيع الخدمة أو تؤجّرها أو تتيح مساحة عملك لأطراف من خارج منشأتك دون اتفاق مكتوب معنا.'),
                        __('ألّا ترفع أو تخزّن محتوى ضاراً أو برمجيات خبيثة عبر الخدمة.'),
                    ] as $item)
                        <li class="flex gap-3">
                            <span class="mt-2.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary/40" aria-hidden="true"></span>
                            <p class="font-body-lg leading-relaxed text-on-surface-variant">{{ $item }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- 5 · customer content --}}
            <section id="your-data" class="tonal-elevation scroll-mt-24 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <span class="material-symbols-outlined" aria-hidden="true">database</span>
                    </div>
                    <h2 class="mt-1.5 font-headline-md text-headline-md text-on-surface">{{ __('بيانات عملك ومحتواك') }}</h2>
                </div>
                <ul class="mt-4 space-y-3">
                    @foreach ([
                        __('كل ما تُدخله إلى مساحة عملك - الطلبات وبنودها، وقائمة الطعام، والعملاء والمستلمون، والبنوك والمحافظ، والمصروفات، والتقارير الناتجة عنها - يبقى ملكاً لمنشأتك. نحن لا ندّعي ملكيته.'),
                        __('نعالج هذه البيانات نيابةً عنك ولتشغيل الخدمة فقط: عرضها لك، وحفظها، ونسخها احتياطياً، ومساعدتك عندما تطلب الدعم.'),
                        __('أنت مسؤول عن مشروعية ما تُدخله، وعن أي بيانات شخصية تخصّ عملاءك تختار تخزينها لدينا - مثل أسماء المستلمين وأرقام هواتفهم - وعن الحصول على أي موافقة يتطلّبها القانون المطبَّق عليك.'),
                        __('عند تعليم طلب بأنه مدفوع عبر تحويل، قد تُرسل الخدمة إشعار واتساب إلى رقم البنك أو المحفظة المسجَّل في مساحة عملك. أنت من يحدّد هذا الرقم، وأنت المسؤول عن صحّته وعن ملاءمة إرسال الإشعارات إليه.'),
                        __('يمكنك أثناء سريان ترخيصك طباعة الإيصالات وتصدير التقارير من داخل الخدمة، ونشجّعك على الاحتفاظ بنسخك الخاصة من السجلات التي تحتاجها لأغراضك المحاسبية.'),
                    ] as $item)
                        <li class="flex gap-3">
                            <span class="mt-2.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary/40" aria-hidden="true"></span>
                            <p class="font-body-lg leading-relaxed text-on-surface-variant">{{ $item }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- 6 · availability --}}
            <section id="availability" class="tonal-elevation scroll-mt-24 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <span class="material-symbols-outlined" aria-hidden="true">cloud_sync</span>
                    </div>
                    <h2 class="mt-1.5 font-headline-md text-headline-md text-on-surface">{{ __('التوافر والصيانة والنسخ الاحتياطية') }}</h2>
                </div>
                <ul class="mt-4 space-y-3">
                    @foreach ([
                        __('نبذل جهداً معقولاً لإبقاء الخدمة متاحة وسريعة، لكننا لا نلتزم في هذه الشروط بنسبة توافر محدّدة.'),
                        __('قد نُجري صيانة أو تحديثات تسبّب انقطاعاً مؤقتاً. نحاول جدولة الصيانة المخطّطة خارج أوقات الذروة والتنبيه إليها مسبقاً كلما أمكن.'),
                        __('نُجري نسخاً احتياطية لقواعد البيانات كجزء من تشغيل المنصّة. النسخ الاحتياطي إجراء تشغيلي لاستعادة الخدمة عند الحاجة، وليس بديلاً عن احتفاظك بسجلاتك الخاصة.'),
                        __('تعتمد بعض الوظائف على خدمات خارجية، مثل إرسال إشعارات واتساب. قد يؤدي انقطاع تلك الخدمات إلى تعطّل هذه الوظائف وحدها دون بقية الخدمة.'),
                        __('تُرسل واجهة المزامنة الطلبات والمصروفات التي أنشأتها دون اتصال عند عودة الاتصال. تقع عليك مسؤولية التأكد من اكتمال المزامنة قبل الاعتماد على البيانات في تقاريرك.'),
                    ] as $item)
                        <li class="flex gap-3">
                            <span class="mt-2.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary/40" aria-hidden="true"></span>
                            <p class="font-body-lg leading-relaxed text-on-surface-variant">{{ $item }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- 7 · suspension & termination --}}
            <section id="termination" class="tonal-elevation scroll-mt-24 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <span class="material-symbols-outlined" aria-hidden="true">block</span>
                    </div>
                    <h2 class="mt-1.5 font-headline-md text-headline-md text-on-surface">{{ __('الإيقاف وإنهاء الخدمة') }}</h2>
                </div>
                <ul class="mt-4 space-y-3">
                    @foreach ([
                        __('يمكنك التوقّف عن استخدام الخدمة متى شئت، وطلب إنهاء ترخيص منشأتك بمراسلتنا.'),
                        __('قد نوقف الوصول أو نُنهيه إذا انتهت صلاحية الترخيص دون تجديد، أو عند مخالفة جسيمة لبند الاستخدام المقبول، أو عند وجود خطر أمني أو قانوني يستدعي التدخّل الفوري.'),
                        __('في المخالفات غير الجسيمة نحاول تنبيهك ومنحك مهلة للتصحيح قبل أي إيقاف، كلما كان ذلك ممكناً وآمناً.'),
                        __('بعد إنهاء الترخيص تبقى بيانات مساحة عملك متاحة لفترة معقولة يمكنك خلالها طلب نسخة منها، ثم قد نحذف قاعدة البيانات والنسخ الاحتياطية المرتبطة بها. يُفضَّل طلب نسختك قبل الإنهاء.'),
                        __('المبالغ المدفوعة عن مدة اشتراك مستهلَكة لا تُسترد، ما لم نتفق على غير ذلك كتابةً.'),
                    ] as $item)
                        <li class="flex gap-3">
                            <span class="mt-2.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary/40" aria-hidden="true"></span>
                            <p class="font-body-lg leading-relaxed text-on-surface-variant">{{ $item }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- 8 · liability --}}
            <section id="liability" class="tonal-elevation scroll-mt-24 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <span class="material-symbols-outlined" aria-hidden="true">balance</span>
                    </div>
                    <h2 class="mt-1.5 font-headline-md text-headline-md text-on-surface">{{ __('حدود المسؤولية وإخلاء الضمانات') }}</h2>
                </div>
                <ul class="mt-4 space-y-3">
                    @foreach ([
                        __('تُقدَّم الخدمة «كما هي». لا نضمن خلوّها التام من الأخطاء ولا استمرارها دون انقطاع، ولا نضمن ملاءمتها لغرض خاص لديك لم نتفق عليه.'),
                        __('الخدمة أداة لتسجيل بيانات عملك وإدارتها؛ وهي ليست استشارة محاسبية أو قانونية أو ضريبية، ولا تغني عن سجلاتك الرسمية أو عن مراجعة مختصّ.'),
                        __('لا نتحمّل مسؤولية الخسائر غير المباشرة أو التبعية، مثل فوات الربح أو فقدان العملاء أو الأضرار الناتجة عن قرارات اتّخذتها بناءً على مخرجات الخدمة.'),
                        __('لا نتحمّل مسؤولية الأخطاء الناتجة عن بيانات أدخلتها أنت أو أعضاء فريقك، ولا عن استخدام غير مصرّح به سببه إهمال في حماية كلمة المرور أو رمز الترخيص.'),
                        __('في جميع الأحوال، يقتصر إجمالي مسؤوليتنا تجاهك عن أي مطالبة على ما دفعته فعلياً مقابل الاشتراك خلال الفترة السابقة مباشرةً للحدث محلّ المطالبة.'),
                        __('لا يحدّ أيٌّ مما سبق من مسؤولية لا يجيز القانون المطبَّق استبعادها أو تحديدها.'),
                    ] as $item)
                        <li class="flex gap-3">
                            <span class="mt-2.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary/40" aria-hidden="true"></span>
                            <p class="font-body-lg leading-relaxed text-on-surface-variant">{{ $item }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- 9 · changes --}}
            <section id="changes" class="tonal-elevation scroll-mt-24 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <span class="material-symbols-outlined" aria-hidden="true">update</span>
                    </div>
                    <h2 class="mt-1.5 font-headline-md text-headline-md text-on-surface">{{ __('تعديل هذه الشروط') }}</h2>
                </div>
                <ul class="mt-4 space-y-3">
                    @foreach ([
                        __('قد نحدّث هذه الشروط لمواكبة تطوّر الخدمة أو لمتطلّبات قانونية أو تشغيلية.'),
                        __('النسخة المنشورة على هذه الصفحة هي النسخة السارية دائماً، ويظهر تاريخ آخر تحديث في أعلاها.'),
                        __('عند إجراء تغيير جوهري، نُعلمك قبل سريانه بوقت معقول عبر البريد الإلكتروني المسجَّل لحساب المالك أو بإشعار داخل الخدمة.'),
                        __('استمرارك في استخدام الخدمة بعد سريان التغيير يعني قبولك للنسخة المحدَّثة من الشروط.'),
                    ] as $item)
                        <li class="flex gap-3">
                            <span class="mt-2.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary/40" aria-hidden="true"></span>
                            <p class="font-body-lg leading-relaxed text-on-surface-variant">{{ $item }}</p>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-5 flex flex-wrap items-center gap-2 rounded-2xl bg-surface-container-low p-4">
                    <span class="material-symbols-outlined !text-xl text-primary" aria-hidden="true">mail</span>
                    <span class="font-body-sm text-on-surface-variant">{{ __('لأي استفسار حول هذه الشروط، راسلنا على:') }}</span>
                    <a href="mailto:{{  }}" class="font-body-sm font-bold text-primary underline-offset-4 hover:underline" dir="ltr" title="{{  }}">{{  }}</a>
                </div>
            </section>

            {{-- ── lawyer-review note ─────────────────────────────────────── --}}
            <aside class="rounded-3xl border border-secondary/40 bg-secondary/5 p-5 md:p-6" role="note">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-secondary/15 text-secondary">
                        <span class="material-symbols-outlined" aria-hidden="true">gavel</span>
                    </div>
                    <div>
                        <h2 class="font-headline-md text-headline-md text-on-surface">{{ __('ملاحظة: هذه الصفحة نموذج، وليست استشارة قانونية') }}</h2>
                        <p class="mt-2 font-body-lg leading-relaxed text-on-surface-variant">
                            {{ __('كُتب هذا النص استناداً إلى ما تفعله المنصّة فعلياً، بهدف أن يكون نقطة انطلاق واضحة ومفهومة. لكنه ليس مستنداً قانونياً نهائياً: قبل الاعتماد عليه في بيئة الإنتاج، يجب أن يراجعه محامٍ مؤهّل ويكيّفه وفق القانون المطبَّق على نشاطك وبلد تسجيله.') }}
                        </p>
                    </div>
                </div>
            </aside>

        </div>
    </section>
@endsection
