@extends('layouts.admin')

@section('title', __('الملف الشخصي - حوالاتي'))

@section('content')
    {{--
        `pw` mirrors UpdateAdminProfileRequest's rules exactly (min 8, mixed case,
        numbers) so the live checklist can never promise something the server then
        rejects. If that rule ever changes, change both together.
    --}}
    <div class="page-enter space-y-6"
        x-data="{
            submitting: false,
            pw: '',
            confirm: '',
            get rules() {
                return {
                    length: this.pw.length >= 8,
                    mixed: /[a-z]/.test(this.pw) && /[A-Z]/.test(this.pw),
                    number: /[0-9]/.test(this.pw),
                };
            },
            get met() { return Object.values(this.rules).filter(Boolean).length; },
            get changing() { return this.pw.length > 0 || this.confirm.length > 0; },
            get mismatch() { return this.confirm.length > 0 && this.pw !== this.confirm; },
        }">

        {{-- ── Identity banner ─────────────────────────────────────────────
             Fixed-dark in both themes, so its text is literal white rather than
             surface tokens (which invert).

             Flat `bg-primary-container` rather than a gradient: the two blurred
             orbs are what give the block depth, so it does not need one. The
             token is deliberately identical in :root and .dark, so this stays
             the same deep navy in both themes and the literal `text-white`
             below is always correct.

             Alignment note: the name/email carry their bidi on an inner <span>,
             not on the block. Putting `dir` on the block itself made a Latin
             name left-align while the badges below it stayed start-aligned,
             so the three lines disagreed with each other. --}}
        <section class="reveal-up relative overflow-hidden rounded-3xl bg-primary-container shadow-xl ring-1 ring-white/10">
            <div class="pointer-events-none absolute -start-20 -top-24 h-72 w-72 rounded-full bg-secondary-container/30 blur-3xl motion-safe:animate-[admin-orb_16s_ease-in-out_infinite]"></div>
            <div class="pointer-events-none absolute -bottom-28 -end-16 h-80 w-80 rounded-full bg-primary-fixed-dim/25 blur-3xl motion-safe:animate-[admin-orb_20s_ease-in-out_infinite_reverse]"></div>

            <div class="relative flex flex-col items-center gap-5 p-6 text-center sm:flex-row sm:items-center sm:gap-6 sm:p-8 sm:text-start">
                <span class="grid h-20 w-20 flex-shrink-0 place-items-center rounded-2xl bg-white/20 font-display-lg text-3xl text-white shadow-lg ring-1 ring-white/30 backdrop-blur-sm sm:h-24 sm:w-24 sm:rounded-3xl sm:text-4xl">
                    {{ mb_substr($admin->name, 0, 1) }}
                </span>

                <div class="min-w-0 flex-1">
                    <h1 class="truncate font-display-lg text-display-lg text-white"><span dir="auto">{{ $admin->name }}</span></h1>
                    <p class="mt-1 truncate font-body-lg text-white/65"><span dir="ltr">{{ $admin->email }}</span></p>

                    <div class="mt-4 flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1.5 font-body-sm text-[12px] font-bold text-[#1e2a5e]">
                            <span class="material-symbols-outlined !text-base">shield_person</span>
                            {{ __('المشرف العام') }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-white/25 bg-white/10 px-3 py-1.5 font-body-sm text-[12px] font-medium text-white/85 backdrop-blur">
                            <span class="material-symbols-outlined !text-base">calendar_month</span>
                            {{ __('عضو منذ') }} {{ $admin->created_at?->translatedFormat('F Y') ?? '-' }}
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <form method="POST" action="{{ route('admin.profile.update') }}" class="space-y-6" @submit="submitting = true">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
                {{-- ── Account details ───────────────────────────────────── --}}
                <section class="reveal-up tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6 lg:col-span-3" style="--d:80ms">
                    <div class="mb-6 flex items-center gap-3">
                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <span class="material-symbols-outlined">badge</span>
                        </span>
                        <div>
                            <h2 class="font-headline-md text-headline-md text-on-surface">{{ __('بياناتي') }}</h2>
                            <p class="font-body-sm text-body-sm text-on-surface-variant">{{ __('الاسم والبريد الإلكتروني') }}</p>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <x-form-field
                            name="name"
                            label="{{ __('الاسم') }}"
                            icon="person"
                            :value="old('name', $admin->name)" />

                        <x-form-field
                            name="email"
                            type="email"
                            label="{{ __('البريد الإلكتروني') }}"
                            icon="mail"
                            dir="ltr"
                            :value="old('email', $admin->email)" />
                    </div>
                </section>

                {{-- ── Security ──────────────────────────────────────────── --}}
                <section class="reveal-up tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6 lg:col-span-2" style="--d:160ms">
                    <div class="mb-6 flex items-center gap-3">
                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-secondary/10 text-secondary">
                            <span class="material-symbols-outlined">lock</span>
                        </span>
                        <div>
                            <h2 class="font-headline-md text-headline-md text-on-surface">{{ __('الأمان') }}</h2>
                            <p class="font-body-sm text-body-sm text-on-surface-variant">{{ __('اتركه فارغاً للإبقاء على كلمة المرور') }}</p>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div class="space-y-2">
                            <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" for="password">
                                {{ __('كلمة المرور الجديدة') }}
                            </label>
                            <div class="relative" x-data="{ show: false }">
                                <span class="material-symbols-outlined pointer-events-none absolute left-0 top-1/2 -translate-y-1/2 pl-3 text-outline">lock</span>
                                <input id="password" name="password" x-model="pw" :type="show ? 'text' : 'password'"
                                    dir="ltr" autocomplete="new-password"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest py-3 pe-12 ps-11 text-body-lg outline-none transition-all focus:border-transparent focus:ring-2 focus:ring-primary" />
                                <button type="button" @click="show = !show"
                                    :aria-label="show ? '{{ __('إخفاء كلمة المرور') }}' : '{{ __('إظهار كلمة المرور') }}'"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-outline transition-colors hover:text-primary">
                                    <span class="material-symbols-outlined" x-text="show ? 'visibility_off' : 'visibility'"></span>
                                </button>
                            </div>
                            @error('password')
                                <p class="font-body-sm text-body-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Strength meter + checklist, revealed only once the user
                             starts typing so the resting state stays calm. --}}
                        <div x-show="changing" x-cloak style="display:none" class="space-y-3">
                            <div class="flex gap-1.5" role="presentation">
                                @for ($i = 1; $i <= 3; $i++)
                                    <span class="h-1.5 flex-1 rounded-full bg-surface-container-high transition-colors duration-300"
                                        :class="met >= {{ $i }} && (met === 3 ? 'bg-success' : met === 2 ? 'bg-secondary' : 'bg-error')"></span>
                                @endfor
                            </div>

                            <ul class="space-y-1.5">
                                @foreach ([
                                    ['key' => 'length', 'label' => __('٨ أحرف على الأقل')],
                                    ['key' => 'mixed', 'label' => __('حرف كبير وحرف صغير')],
                                    ['key' => 'number', 'label' => __('رقم واحد على الأقل')],
                                ] as $rule)
                                    <li class="flex items-center gap-2 font-body-sm text-[13px] transition-colors"
                                        :class="rules.{{ $rule['key'] }} ? 'text-success' : 'text-on-surface-variant'">
                                        <span class="material-symbols-outlined !text-base"
                                            x-text="rules.{{ $rule['key'] }} ? 'check_circle' : 'radio_button_unchecked'"></span>
                                        {{ $rule['label'] }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="space-y-2">
                            <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" for="password_confirmation">
                                {{ __('تأكيد كلمة المرور') }}
                            </label>
                            <div class="relative">
                                <span class="material-symbols-outlined pointer-events-none absolute left-0 top-1/2 -translate-y-1/2 pl-3 text-outline">lock</span>
                                <input id="password_confirmation" name="password_confirmation" x-model="confirm" type="password"
                                    dir="ltr" autocomplete="new-password"
                                    class="w-full rounded-xl border bg-surface-container-lowest py-3 pe-4 ps-11 text-body-lg outline-none transition-all focus:border-transparent focus:ring-2"
                                    :class="mismatch ? 'border-error focus:ring-error' : 'border-outline-variant focus:ring-primary'" />
                            </div>
                            <p x-show="mismatch" x-cloak style="display:none" class="flex items-center gap-1 font-body-sm text-body-sm text-error">
                                <span class="material-symbols-outlined !text-base">error</span>
                                {{ __('كلمتا المرور غير متطابقتين') }}
                            </p>
                        </div>
                    </div>
                </section>
            </div>

            {{-- Sticky action bar so Save stays reachable on a long form. --}}
            <div class="reveal-up sticky bottom-4 z-20 flex items-center justify-between gap-4 rounded-2xl border border-outline-variant/60 bg-surface-container-lowest/90 p-4 shadow-xl backdrop-blur" style="--d:240ms">
                <p class="hidden font-body-sm text-body-sm text-on-surface-variant sm:block">
                    {{ __('لن تُحفظ التغييرات حتى تضغط حفظ.') }}
                </p>
                <button type="submit" :disabled="submitting || mismatch"
                    class="press flex items-center gap-2 rounded-xl bg-primary px-6 py-3 font-body-sm font-bold text-on-primary shadow-lg shadow-primary/25 transition-all hover:bg-primary-container hover:text-on-primary-container hover:shadow-xl disabled:cursor-not-allowed disabled:opacity-50"
                    title="{{ __('حفظ التغييرات') }}">
                    <span class="material-symbols-outlined !text-xl" :class="submitting && 'animate-spin'"
                        x-text="submitting ? 'progress_activity' : 'save'">save</span>
                    <span x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ التغييرات'))">{{ __('حفظ التغييرات') }}</span>
                </button>
            </div>
        </form>
    </div>
@endsection
