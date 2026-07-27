@extends('layouts.guest')

@section('title', __('سياسة الخصوصية - حوالاتي'))

@section('content')
    @php
        // [icon, heading, intro paragraph, bullet points]
        $sections = [
            [
                'list_alt',
                __('البيانات التي نجمعها'),
                __('نجمع ثلاثة أنواع من البيانات فقط: ما تُدخله عند إنشاء الحساب، وما تُدخله أثناء تشغيل عملك، وما يُسجَّل تقنياً لتأمين الحساب.'),
                [
                    __('بيانات الحساب: الاسم، والبريد الإلكتروني، ورقم الهاتف إن أضفته. كلمة المرور لا تُخزَّن كنص، بل تُحفظ بصيغة مُجزَّأة لا يمكن عكسها.'),
                    __('بيانات عملك: الطلبات وبنودها، وأصناف قائمة الطعام وفئاتها، والعملاء والمستفيدون وأرقام هواتفهم، والبنوك والمحافظ، والمصروفات، والتقارير المشتقّة منها.'),
                    __('البيانات التقنية: أحداث تسجيل الدخول، والأجهزة المرتبطة بحسابك، وسجل النشاط الذي يوثّق من فعل ماذا ومتى. تتضمّن الجلسة عنوان IP ومعرّف المتصفح لتمييز الأجهزة.'),
                    __('بيانات الاشتراك: الباقة وحالة الترخيص والمدفوعات المسجّلة مقابل اشتراكك في المنصّة.'),
                ],
            ],
            [
                'dns',
                __('قاعدة بيانات مستقلة لكل عميل'),
                __('حوالاتي نظام متعدد المستأجرين بعزل فعلي: لكل حساب عمل قاعدة بياناته الخاصة. بيانات نشاطك التجاري لا تُخزَّن مختلطة مع بيانات عميل آخر في الجداول نفسها.'),
                [
                    __('الطلبات وقائمة الطعام والعملاء والبنوك والمصروفات وسجل النشاط تعيش كلها داخل قاعدة بيانات حسابك وحده.'),
                    __('قاعدة بيانات المنصّة المركزية تحتفظ فقط بما يلزم لتشغيل الاشتراك: بيانات الحساب، والباقة، والترخيص، والمدفوعات.'),
                    __('أعضاء الفريق الذين تدعوهم يرون بيانات حسابك وحده، وضمن حدود الدور الممنوح لهم.'),
                ],
            ],
            [
                'tune',
                __('كيف نستخدم بياناتك'),
                __('نستخدم البيانات لتشغيل الخدمة التي طلبتها، ولا نستخدمها لغير ذلك.'),
                [
                    __('تشغيل الخدمة: إنشاء الطلبات وحساب مجاميعها، وعرض التقارير، وإصدار الإيصالات.'),
                    __('الإشعارات: إرسال رسالة واتساب إلى رقم البنك أو المحفظة عند تسجيل طلب مدفوع بتحويل، وذلك إن فعّلت هذه الميزة.'),
                    __('الدعم الفني: الاطلاع على الحد الأدنى اللازم من البيانات لحل مشكلة أبلغتنا عنها.'),
                    __('النسخ الاحتياطية: حفظ نسخ دورية لحماية بياناتك من الفقدان.'),
                    __('لا نستخدم بيانات عملك في الإعلانات ولا في تدريب نماذج ذكاء اصطناعي.'),
                ],
            ],
            [
                'share',
                __('المشاركة مع أطراف خارجية'),
                __('لا نبيع بياناتك ولا نؤجّرها ولا نشاركها لأغراض تسويقية. تقتصر المشاركة على ما يلزم تقنياً لتشغيل ميزة طلبتها أنت.'),
                [
                    __('واتساب: عند تفعيل إشعارات واتساب، يُمرَّر نص الرسالة ورقم المستلم إلى واجهة واتساب السحابية من Meta لإتمام الإرسال، وتخضع تلك المعالجة لسياسات Meta.'),
                    __('الاستضافة: تُخزَّن قواعد البيانات والنسخ الاحتياطية على الخوادم التي تعمل عليها المنصّة.'),
                    __('قد نفصح عن بيانات استجابةً لطلب قانوني صحيح ومُلزِم، وسنُعلمك بذلك ما لم يمنعنا القانون من إعلامك.'),
                ],
            ],
            [
                'backup',
                __('الاحتفاظ بالبيانات والنسخ الاحتياطية'),
                __('نحتفظ ببياناتك ما دام حسابك نشطاً، ثم للمدة اللازمة لأغراض المحاسبة والتدقيق.'),
                [
                    __('حذف سجل مالي مثل طلب أو مصروف من داخل التطبيق هو حذف ناعم: يختفي السجل من قوائمك لكنه يبقى محفوظاً لأغراض التدقيق وسلامة التقارير.'),
                    __('سجل النشاط غير قابل للتعديل من داخل التطبيق، لأن قيمته كلها في كونه غير قابل للتغيير.'),
                    __('تُؤخذ النسخ الاحتياطية بشكل دوري، وقد تبقى البيانات المحذوفة موجودة داخل نسخة سابقة إلى أن تنتهي دورة الاحتفاظ بها.'),
                    __('عند إنهاء الاشتراك نهائياً يمكنك طلب حذف قاعدة بيانات حسابك بالكامل.'),
                ],
            ],
            [
                'checklist',
                __('حقوقك في بياناتك'),
                __('البيانات التي تُدخلها في حوالاتي تخصّك أنت، ويمكنك في أي وقت أن تمارس ما يلي:'),
                [
                    __('الاطّلاع: تصفّح كل طلباتك وعملائك ومصروفاتك مباشرة من داخل التطبيق.'),
                    __('التصحيح: تعديل بيانات حسابك وعملائك وأصنافك من صفحاتها.'),
                    __('التصدير: تنزيل بياناتك بصيغة CSV، والحصول على التقارير والإيصالات بصيغة PDF.'),
                    __('الحذف: طلب حذف حسابك وبياناته، مع مراعاة مدد الاحتفاظ المذكورة أعلاه.'),
                    __('الاعتراض: إيقاف إشعارات واتساب من إعدادات الحساب متى شئت.'),
                ],
            ],
            [
                'shield',
                __('كيف نحمي بياناتك'),
                __('نصف هنا ما هو مطبَّق فعلاً في المنتج، دون ادعاء أي شهادات أو اعتمادات لا نملكها.'),
                [
                    __('كلمات المرور تُخزَّن مُجزَّأة، ولا يستطيع أحد قراءتها، بمن في ذلك نحن.'),
                    __('صلاحيات قائمة على الأدوار: المالك والمدير والموظف، ولكل دور حدود واضحة لما يمكنه رؤيته وفعله.'),
                    __('الجلسات مرتبطة بأجهزة يمكنك مراجعتها وإلغاء أي منها من صفحة الأجهزة.'),
                    __('محاولات تسجيل الدخول محدودة المعدّل للحدّ من هجمات تخمين كلمات المرور.'),
                    __('سجل نشاط يوثّق العمليات الحسّاسة داخل حسابك.'),
                    __('لا يوجد نظام آمن بصورة مطلقة، واختيار كلمة مرور قوية وعدم مشاركة حسابك جزء أساسي من الحماية.'),
                ],
            ],
            [
                'cookie',
                __('ملفات تعريف الارتباط والتخزين المحلي'),
                __('نستخدم أقل قدر ممكن من التخزين داخل متصفحك، ولا نستخدم أي أدوات تتبّع إعلاني.'),
                [
                    __('ملف ارتباط الجلسة: ضروري لإبقائك مسجّل الدخول ولحماية النماذج من التزوير، ولا يمكن إيقافه دون تعطيل تسجيل الدخول.'),
                    __('تفضيل اللغة بين العربية والإنجليزية، وتفضيل المظهر الفاتح أو الداكن، يُحفظان محلياً في متصفحك ولا يُرسلان إلى أي طرف خارجي.'),
                    __('لا نستخدم ملفات ارتباط إعلانية ولا أدوات تتبّع عبر المواقع.'),
                ],
            ],
            [
                'update',
                __('تحديثات هذه السياسة'),
                __('قد نُحدّث هذه السياسة عند إضافة ميزات جديدة أو تغيير طريقة معالجة البيانات. سنُغيّر تاريخ آخر تحديث أعلى الصفحة، وإذا كان التغيير جوهرياً سنُشعرك داخل التطبيق قبل سريانه.'),
                [],
            ],
        ];
    @endphp

    <section class="page-enter mx-auto w-full max-w-4xl px-inline-md py-16 md:px-inline-lg lg:col-span-2">
        <header class="reveal-up text-center" style="--d:80ms">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-3xl bg-primary/10 text-primary">
                <span class="material-symbols-outlined !text-4xl" aria-hidden="true">privacy_tip</span>
            </div>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('سياسة الخصوصية') }}</h1>
            <p class="mx-auto mt-3 max-w-2xl font-body-lg leading-relaxed text-on-surface-variant">{{ __('كيف نجمع بياناتك ونستخدمها ونحميها داخل حوالاتي.') }}</p>
            <p class="mt-2 font-body-sm text-outline">{{ __('آخر تحديث: يوليو 2026') }}</p>
        </header>

        <div class="stagger mt-10 space-y-5">
            {{-- scope --}}
            <p class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-low p-5 font-body-lg leading-relaxed text-on-surface-variant md:p-6">
                {{ __('توضّح هذه السياسة ما الذي يُجمع من بيانات عند استخدامك لحوالاتي، ولماذا يُجمع، ومن يستطيع الوصول إليه. وهي تنطبق على حسابات الأعمال وعلى أعضاء الفريق الذين تدعوهم للعمل داخل حسابك.') }}
            </p>

            @foreach ($sections as [$icon, $heading, $intro, $points])
                <article class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                            <span class="material-symbols-outlined" aria-hidden="true">{{ $icon }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 class="font-headline-md text-headline-md text-on-surface">{{ $heading }}</h2>
                            <p class="mt-2 font-body-lg leading-relaxed text-on-surface-variant">{{ $intro }}</p>

                            @if (! empty($points))
                                <ul class="mt-4 space-y-2.5 border-t border-outline-variant/50 pt-4">
                                    @foreach ($points as $point)
                                        <li class="flex items-start gap-2.5 text-start font-body-sm leading-relaxed text-on-surface-variant">
                                            <span class="material-symbols-outlined mt-0.5 flex-shrink-0 !text-base text-primary rtl:-scale-x-100" aria-hidden="true">chevron_right</span>
                                            <span>{{ $point }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach

            {{-- contact --}}
            <article class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-primary/5 p-5 md:p-6">
                <div class="flex flex-col items-start gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-start gap-4">
                        <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-secondary-container/15 text-secondary">
                            <span class="material-symbols-outlined" aria-hidden="true">contact_support</span>
                        </div>
                        <div class="min-w-0">
                            <h2 class="font-headline-md text-headline-md text-on-surface">{{ __('التواصل بخصوص الخصوصية') }}</h2>
                            <p class="mt-2 font-body-lg leading-relaxed text-on-surface-variant">{{ __('لأي سؤال عن هذه السياسة، أو لطلب نسخة من بياناتك أو حذفها، راسلنا وسنردّ عليك في أقرب وقت ممكن.') }}</p>
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

            {{-- template / legal-review notice --}}
            <aside class="rounded-3xl border border-dashed border-tertiary/50 bg-tertiary-container/15 p-5 md:p-6" role="note">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-tertiary/10 text-tertiary">
                        <span class="material-symbols-outlined" aria-hidden="true">gavel</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-headline-md text-headline-md text-on-surface">{{ __('هذه الوثيقة نموذج، وليست استشارة قانونية') }}</h2>
                        <p class="mt-2 font-body-sm leading-relaxed text-on-surface-variant">{{ __('أُعدّت هذه الصفحة لوصف الطريقة التي يعمل بها حوالاتي فعلياً، وهي نموذج قابل للتعديل لا يغني عن الاستشارة القانونية. راجعها مع محامٍ مؤهَّل وعدّلها بما يوافق القوانين السارية في بلدك قبل اعتمادها في بيئة الإنتاج.') }}</p>
                    </div>
                </div>
            </aside>
        </div>
    </section>
@endsection
