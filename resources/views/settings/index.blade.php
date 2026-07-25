@extends('layouts.app')

@section('title', __('الإعدادات - حوالاتي'))

@section('content')
    @php
        $statusLabels = ['trial' => __('تجريبي'), 'active' => __('نشط'), 'suspended' => __('موقوف'), 'expired' => __('منتهٍ')];
    @endphp

    <div class="page-enter mx-auto max-w-3xl space-y-6">
        <header>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الإعدادات') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('أدر بياناتك الشخصية وكلمة مرورك.') }}</p>
        </header>

        {{-- profile + password --}}
        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            <div class="mb-6 flex items-center gap-3">
                <div class="grid h-12 w-12 place-items-center rounded-full bg-primary-container text-lg font-bold text-on-primary-container">{{ mb_substr($member->name, 0, 1) }}</div>
                <div>
                    <h2 class="font-headline-md text-headline-md text-on-surface">{{ __('ملفي الشخصي') }}</h2>
                    <p class="font-body-sm text-outline" dir="ltr">{{ $member->email }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('settings.profile.update') }}" class="space-y-5" x-data="{ submitting: false }" @submit="submitting = true">
                @csrf
                @method('PUT')

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label for="name" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الاسم') }}</label>
                        <input id="name" name="name" value="{{ old('name', $member->name) }}" required
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                    <div class="space-y-1.5">
                        <label for="email" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('البريد الإلكتروني') }}</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $member->email) }}" required dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                </div>

                <div class="border-t border-outline-variant/50 pt-5">
                    <p class="mb-4 font-body-sm text-on-surface-variant">{{ __('لتغيير كلمة المرور، املأ الحقلين أدناه — وإلا اتركهما فارغين.') }}</p>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label for="password" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('كلمة مرور جديدة') }}</label>
                            <input id="password" type="password" name="password" dir="ltr" placeholder="••••••" autocomplete="new-password"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        </div>
                        <div class="space-y-1.5">
                            <label for="password_confirmation" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('تأكيد كلمة المرور') }}</label>
                            <input id="password_confirmation" type="password" name="password_confirmation" dir="ltr" placeholder="••••••" autocomplete="new-password"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" :disabled="submitting" class="flex items-center gap-2 rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50">
                        <span class="material-symbols-outlined !text-xl" x-show="!submitting">save</span>
                        <span x-text="submitting ? '{{ __('جارٍ الحفظ...') }}' : '{{ __('حفظ التغييرات') }}'"></span>
                    </button>
                </div>
            </form>
        </section>

        {{-- business / license info --}}
        @if ($license)
            <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                <h2 class="mb-6 font-headline-md text-headline-md text-on-surface">{{ __('معلومات النشاط') }}</h2>
                <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                    @php
                        $rows = [
                            ['storefront', 'اسم النشاط', $license->customer_name],
                            ['qr_code_2', 'رمز الترخيص', $license->license_code],
                            ['workspace_premium', 'الباقة', $license->package?->name ?? '—'],
                            ['verified', 'حالة الترخيص', $statusLabels[$license->status->value] ?? $license->status->value],
                            ['event', 'ينتهي في', $license->expired_at?->translatedFormat('j F Y') ?? '—'],
                            ['account_balance_wallet', 'الرصيد', number_format($license->customer_balance, 2) . ' ₪'],
                        ];
                    @endphp
                    @foreach ($rows as [$icon, $label, $value])
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <span class="material-symbols-outlined !text-xl">{{ $icon }}</span>
                            </div>
                            <div class="min-w-0">
                                <dt class="font-body-sm text-outline">{{ __($label) }}</dt>
                                <dd class="truncate font-body-lg font-bold text-on-surface" @if(in_array($label, ['رمز الترخيص','الرصيد'])) dir="ltr" @endif>{{ $value }}</dd>
                            </div>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif
    </div>
@endsection
