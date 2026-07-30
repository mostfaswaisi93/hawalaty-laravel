@extends('layouts.app')

@section('title', __('إعدادات واتساب - حوالاتي'))

@section('content')
    @php
        $field = 'w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary';
        $label = 'block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant';
        $hint = 'font-body-sm text-body-sm text-on-surface-variant';

        $driverLabels = [
            'log' => __('تسجيل فقط - لا يُرسل شيئًا'),
            'cloud' => __('إرسال حقيقي عبر واتساب'),
            'null' => __('معطّل بصمت'),
        ];
    @endphp

    <div class="page-enter space-y-6">
        <header>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('إعدادات واتساب') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('بيانات حسابك على واتساب. كل نسخة تضع بياناتها الخاصة.') }}</p>
        </header>

        @if(session('success'))
            <div class="flex items-start gap-2.5 rounded-xl bg-success-container/20 p-3 text-success">
                <span class="material-symbols-outlined !text-lg">check_circle</span>
                <p class="font-body-sm text-body-sm">{{ session('success') }}</p>
            </div>
        @endif

        @if(session('error'))
            <div class="flex items-start gap-2.5 rounded-xl bg-error-container/40 p-3 text-error">
                <span class="material-symbols-outlined !text-lg">error</span>
                <p class="font-body-sm text-body-sm">{{ session('error') }}</p>
            </div>
        @endif

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-5">

            <section class="reveal-up tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6 lg:col-span-3">
                <form method="POST" action="{{ route('whatsapp.settings.update') }}" class="space-y-5"
                      x-data="{ submitting: false, driver: @js(old('driver', $values['whatsapp_driver'])) }"
                      @submit="submitting = true">
                    @csrf
                    @method('PUT')

                    <div class="space-y-1.5">
                        <label for="driver" class="{{ $label }}">{{ __('وضع الإرسال') }}</label>
                        <select id="driver" name="driver" x-model="driver" class="{{ $field }}">
                            @foreach($driverLabels as $value => $text)
                                <option value="{{ $value }}" @selected(old('driver', $values['whatsapp_driver']) === $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                        @error('driver')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                        <p class="{{ $hint }}">{{ __('ابدأ بـ «تسجيل فقط» للتجربة - تظهر الرسالة في السجل دون إرسالها فعليًا.') }}</p>
                    </div>

                    <label class="flex items-center gap-3 rounded-xl bg-surface-container-low p-3">
                        <input type="checkbox" name="enabled" value="1" class="h-5 w-5 rounded"
                               @checked(old('enabled', $values['whatsapp_enabled']) == '1') />
                        <span class="font-body-sm text-body-sm text-on-surface">{{ __('تفعيل إشعارات واتساب') }}</span>
                    </label>

                    {{-- Credentials only matter for the cloud driver; hiding them
                         keeps the log-driver demo path down to two controls. --}}
                    <div x-show="driver === 'cloud'" x-cloak class="space-y-5 border-t border-outline-variant/50 pt-5">

                        <div class="flex items-start gap-2.5 rounded-xl bg-surface-container-low p-3">
                            <span class="material-symbols-outlined !text-lg text-on-surface-variant">info</span>
                            <p class="{{ $hint }}">{{ __('من لوحة Meta for Developers ← تطبيقك ← WhatsApp ← API Setup. اترك حقلي الرمز والسر فارغين للإبقاء على المحفوظ.') }}</p>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label for="phoneNumberId" class="{{ $label }}">{{ __('معرّف الرقم') }}</label>
                                <input id="phoneNumberId" name="phoneNumberId" dir="ltr" inputmode="numeric"
                                       value="{{ old('phoneNumberId', $values['whatsapp_phone_number_id']) }}"
                                       placeholder="1194039683796457" class="{{ $field }}" />
                                @error('phoneNumberId')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="space-y-1.5">
                                <label for="wabaId" class="{{ $label }}">{{ __('معرّف حساب الأعمال') }}</label>
                                <input id="wabaId" name="wabaId" dir="ltr" inputmode="numeric"
                                       value="{{ old('wabaId', $values['whatsapp_waba_id']) }}"
                                       placeholder="1199303452369438" class="{{ $field }}" />
                                @error('wabaId')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label for="token" class="{{ $label }}">{{ __('رمز الوصول') }}</label>
                            <input id="token" name="token" type="password" dir="ltr" autocomplete="off"
                                   placeholder="{{ $values['whatsapp_token'] ?: 'EAAT...' }}" class="{{ $field }}" />
                            @error('token')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                            <p class="{{ $hint }}">{{ __('استخدم رمز System User الدائم - الرمز المؤقت ينتهي خلال 24 ساعة.') }}</p>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label for="appSecret" class="{{ $label }}">{{ __('سر التطبيق') }}</label>
                                <input id="appSecret" name="appSecret" type="password" dir="ltr" autocomplete="off"
                                       placeholder="{{ $values['whatsapp_app_secret'] ?: '••••' }}" class="{{ $field }}" />
                                @error('appSecret')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="space-y-1.5">
                                <label for="verifyToken" class="{{ $label }}">{{ __('رمز التحقق') }}</label>
                                <input id="verifyToken" name="verifyToken" dir="ltr" autocomplete="off"
                                       value="{{ old('verifyToken', $values['whatsapp_verify_token']) }}" class="{{ $field }}" />
                                @error('verifyToken')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label for="templateNewOrder" class="{{ $label }}">{{ __('اسم القالب') }}</label>
                                <input id="templateNewOrder" name="templateNewOrder" dir="ltr"
                                       value="{{ old('templateNewOrder', $values['whatsapp_template_new_order']) }}"
                                       placeholder="new_order" class="{{ $field }}" />
                                @error('templateNewOrder')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="space-y-1.5">
                                <label for="templateLanguage" class="{{ $label }}">{{ __('لغة القالب') }}</label>
                                <input id="templateLanguage" name="templateLanguage" dir="ltr"
                                       value="{{ old('templateLanguage', $values['whatsapp_template_language']) }}"
                                       placeholder="ar" class="{{ $field }}" />
                                @error('templateLanguage')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="flex items-start gap-2.5 rounded-xl bg-surface-container-low p-3">
                            <span class="material-symbols-outlined !text-lg text-on-surface-variant">warning</span>
                            {{-- يُبنى بالتجميع عمدًا: كتابة القوسين المزدوجين حرفيًا هنا
                                 يجعل Blade يظنّهما بداية تعبير echo فيفشل الملف بـ ParseError. --}}
                            @php($templateVar = '{'.'{1}}')
                            <p class="{{ $hint }}">{{ __('القالب يجب أن يحتوي متغيّرًا واحدًا :var — يُمرَّر نص الطلب كاملًا فيه. عدد مختلف يُرجع الخطأ 132000.', ['var' => $templateVar]) }}</p>
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-outline-variant/50 pt-5">
                        <button type="submit" :disabled="submitting"
                            class="press flex items-center gap-2 rounded-xl bg-primary px-6 py-3 font-body-sm font-bold text-on-primary shadow-lg shadow-primary/25 transition-all hover:bg-primary-container hover:text-on-primary-container disabled:cursor-not-allowed disabled:opacity-50">
                            <span class="material-symbols-outlined !text-xl" :class="submitting && 'animate-spin'"
                                  x-text="submitting ? 'progress_activity' : 'save'">save</span>
                            <span x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ التغييرات'))">{{ __('حفظ التغييرات') }}</span>
                        </button>
                    </div>
                </form>
            </section>

            <aside class="space-y-6 lg:col-span-2">

                <section class="reveal-up tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                    <h2 class="mb-4 font-headline-md text-headline-md text-on-surface">{{ __('الحالة') }}</h2>

                    <dl class="space-y-3 font-body-sm text-body-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-on-surface-variant">{{ __('الوضع الفعلي') }}</dt>
                            <dd class="font-bold text-on-surface">{{ $driverLabels[$effectiveDriver] ?? $effectiveDriver }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-on-surface-variant">{{ __('البيانات مكتملة') }}</dt>
                            <dd class="font-bold {{ $configured ? 'text-success' : 'text-error' }}">
                                {{ $configured ? __('نعم') : __('لا') }}
                            </dd>
                        </div>
                    </dl>

                    @unless($configured)
                        <p class="mt-4 rounded-xl bg-surface-container-low p-3 {{ $hint }}">
                            {{ __('بدون رمز ومعرّف رقم، يبقى الإرسال على وضع التسجيل مهما اخترت.') }}
                        </p>
                    @endunless
                </section>

                <section class="reveal-up tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                    <h2 class="mb-2 font-headline-md text-headline-md text-on-surface">{{ __('إرسال رسالة') }}</h2>
                    <p class="mb-4 {{ $hint }}">{{ __('اكتب نصك وأرسله مباشرة دون إنشاء طلب.') }}</p>

                    <form method="POST" action="{{ route('whatsapp.settings.test') }}" class="space-y-3"
                          x-data="{ sending: false, body: @js(old('body', '')) }" @submit="sending = true">
                        @csrf

                        <div class="space-y-1.5">
                            <label for="send-phone" class="{{ $label }}">{{ __('رقم المستلم') }}</label>
                            <input id="send-phone" name="phone" dir="ltr" inputmode="tel" required
                                   list="wa-known-recipients" autocomplete="off" placeholder="0599302930"
                                   value="{{ old('phone') }}" class="{{ $field }}" />

                            @if ($recipients !== [])
                                {{-- أرقام ميتا المسجّلة: الرقم التجريبي لا يرسل لغيرها،
                                     فعرضها كأزرار يمنع خطأ 131030 قبل وقوعه. --}}
                                <datalist id="wa-known-recipients">
                                    @foreach ($recipients as $recipient)
                                        <option value="{{ $recipient }}"></option>
                                    @endforeach
                                </datalist>
                                <div class="flex flex-wrap gap-1.5 pt-1">
                                    @foreach ($recipients as $recipient)
                                        <button type="button" dir="ltr"
                                                @click="document.getElementById('send-phone').value = @js($recipient)"
                                                class="rounded-lg border border-outline-variant bg-surface-container-low px-2.5 py-1 font-mono text-body-sm text-on-surface-variant transition-colors hover:border-primary hover:text-primary">{{ $recipient }}</button>
                                    @endforeach
                                </div>
                            @endif
                            @error('phone')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="send-body" class="{{ $label }}">{{ __('نص الرسالة') }}</label>
                            <textarea id="send-body" name="body" rows="4" x-model="body" maxlength="1000"
                                      placeholder="{{ __('اكتب الرسالة هنا…') }}" class="{{ $field }}"></textarea>
                            <div class="flex items-center justify-between">
                                <span class="{{ $hint }}">{{ __('اتركه فارغًا لإرسال رسالة تجريبية جاهزة.') }}</span>
                                <span class="font-mono text-body-sm text-on-surface-variant" dir="ltr"
                                      x-text="body.length + '/1000'">0/1000</span>
                            </div>
                            @error('body')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                        </div>

                        <button type="submit" :disabled="sending"
                            class="press flex w-full items-center justify-center gap-2 rounded-xl border border-outline-variant px-4 py-3 font-body-sm font-bold text-on-surface transition-all hover:bg-surface-container disabled:opacity-50">
                            <span class="material-symbols-outlined !text-xl" :class="sending && 'animate-spin'"
                                  x-text="sending ? 'progress_activity' : 'send'">send</span>
                            <span x-text="sending ? @js(__('جارٍ الإرسال...')) : @js(__('إرسال'))">{{ __('إرسال') }}</span>
                        </button>
                    </form>
                </section>

                <section class="reveal-up tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                    <h2 class="mb-2 font-headline-md text-headline-md text-on-surface">{{ __('رابط الويبهوك') }}</h2>
                    <p class="mb-3 {{ $hint }}">{{ __('ضعه في Meta ← WhatsApp ← Configuration، مع رمز التحقق أعلاه.') }}</p>
                    <code class="block break-all rounded-xl bg-surface-container-low p-3 font-mono text-body-sm text-on-surface" dir="ltr">{{ $webhookUrl }}</code>
                </section>
            </aside>
        </div>
    </div>
@endsection
