@extends('layouts.app')

@section('title', __('حساب جديد - حوالاتي'))

@php
    // Which currencies each bank/wallet supports, so the currency select can be
    // narrowed to the chosen institution (mirrors StoreAccountRequest's check).
    $currenciesJs = $currencies->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'symbol' => $c->symbol])->values();
    $bankCurrencies = $banks->mapWithKeys(fn ($b) => [$b->id => $b->currencies->pluck('id')->values()]);
    $walletCurrencies = $wallets->mapWithKeys(fn ($w) => [$w->id => $w->currencies->pluck('id')->values()]);
    $cashCurrencies = $cashBoxes->mapWithKeys(fn ($b) => [$b->id => $b->currencies->pluck('id')->values()]);
@endphp

@section('content')
    <div class="page-enter mx-auto max-w-2xl space-y-6"
        x-data="{
            kind: '{{ old('kind', 'bank') }}',
            submitting: false,
            currencies: @js($currenciesJs),
            bankCurrencies: @js($bankCurrencies),
            walletCurrencies: @js($walletCurrencies),
            cashCurrencies: @js($cashCurrencies),
            form: {
                bank_name_id: '{{ old('bank_name_id', '') }}',
                account_type_id: '{{ old('account_type_id', '') }}',
                iban: '{{ old('iban', '') }}',
                wallet_name_id: '{{ old('wallet_name_id', '') }}',
                wallet_type_id: '{{ old('wallet_type_id', '') }}',
                wallet_phone: '{{ old('wallet_phone', '') }}',
                cash_box_id: '{{ old('cash_box_id', '') }}',
                currency_id: '{{ old('currency_id', '') }}',
            },
            supportedIds() {
                const inst = { bank: this.form.bank_name_id, wallet: this.form.wallet_name_id, cash: this.form.cash_box_id }[this.kind];
                const map = { bank: this.bankCurrencies, wallet: this.walletCurrencies, cash: this.cashCurrencies }[this.kind];
                return inst && map[inst] ? map[inst] : [];
            },
            supportedCurrencies() {
                const ids = this.supportedIds();
                return this.currencies.filter(c => ids.includes(c.id));
            },
            onInstitutionChange() {
                if (!this.supportedIds().includes(this.form.currency_id)) this.form.currency_id = '';
            },
        }">

        <header class="flex items-center gap-3">
            <a href="{{ route('accounts.index') }}" class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('رجوع') }}" title="{{ __('رجوع') }}"
                @mouseenter="showTipBelow($el, @js(__('رجوع')))" @mouseleave="hideTip()" @focus="showTipBelow($el, @js(__('رجوع')))" @blur="hideTip()">
                <span class="material-symbols-outlined rtl:-scale-x-100">arrow_back</span>
            </a>
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('حساب جديد') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('افتح حساباً في بنك أو محفظة، ويُفتح له دفتر حركات ورصيد.') }}</p>
            </div>
        </header>

        @if ($currencies->isEmpty() || ($banks->isEmpty() && $wallets->isEmpty()))
            <div class="reveal-up rounded-2xl border border-dashed border-outline-variant bg-surface-container-low p-5 font-body-sm text-on-surface-variant">
                <div class="flex items-start gap-2">
                    <span class="material-symbols-outlined !text-xl text-primary">info</span>
                    <p>{{ __('أضِف عملة وبنكاً أو محفظة (مع عملاتها) من') }} <a href="{{ route('catalogs.index') }}" class="font-bold text-primary hover:underline">{{ __('الكتالوجات') }}</a> {{ __('قبل إنشاء حساب.') }}</p>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('accounts.store') }}" class="space-y-6" @submit="submitting = true">
            @csrf
            <input type="hidden" name="kind" :value="kind">

            {{-- kind selector: نقدي / بنك / محفظة --}}
            <div class="grid grid-cols-3 gap-3">
                @foreach (\App\Enums\AccountKind::cases() as $case)
                    <button type="button" @click="kind = '{{ $case->value }}'; onInstitutionChange()"
                        class="flex items-center gap-3 rounded-2xl border-2 p-4 text-start transition-all"
                        :class="kind === '{{ $case->value }}' ? 'border-primary bg-primary/10 text-primary' : 'border-outline-variant bg-surface-container-lowest text-on-surface-variant hover:border-outline'">
                        <span class="material-symbols-outlined !text-2xl">{{ $case->icon() }}</span>
                        <span class="font-body-lg font-bold">{{ $case->label() }}</span>
                    </button>
                @endforeach
            </div>

            <section class="tonal-elevation space-y-4 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                {{-- ═══ bank fields ═══ --}}
                <div class="space-y-4" x-show="kind === 'bank'" x-cloak>
                    <div class="space-y-1.5">
                        <label for="acc-bank" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('البنك') }}</label>
                        <select id="acc-bank" name="bank_name_id" x-model="form.bank_name_id" @change="onInstitutionChange()" :disabled="kind !== 'bank'"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            <option value="">{{ __('اختر البنك') }}</option>
                            @foreach ($banks as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                            @endforeach
                        </select>
                        @error('bank_name_id')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-1.5">
                            <label for="acc-account-type" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('نوع الحساب') }}</label>
                            <select id="acc-account-type" name="account_type_id" x-model="form.account_type_id" :disabled="kind !== 'bank'"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                <option value="">{{ __('اختر النوع') }}</option>
                                @foreach ($accountTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                            @error('account_type_id')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="space-y-1.5">
                            <label for="acc-iban" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('آيبان (اختياري)') }}</label>
                            <input id="acc-iban" name="iban" x-model="form.iban" dir="ltr" placeholder="PS00 0000 ..."
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            @error('iban')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                {{-- ═══ wallet fields ═══ --}}
                <div class="space-y-4" x-show="kind === 'wallet'" x-cloak>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-1.5">
                            <label for="acc-wallet" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المحفظة') }}</label>
                            <select id="acc-wallet" name="wallet_name_id" x-model="form.wallet_name_id" @change="onInstitutionChange()" :disabled="kind !== 'wallet'"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                <option value="">{{ __('اختر المحفظة') }}</option>
                                @foreach ($wallets as $wallet)
                                    <option value="{{ $wallet->id }}">{{ $wallet->name }}</option>
                                @endforeach
                            </select>
                            @error('wallet_name_id')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="space-y-1.5">
                            <label for="acc-wallet-type" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('نوع المحفظة') }}</label>
                            <select id="acc-wallet-type" name="wallet_type_id" x-model="form.wallet_type_id" :disabled="kind !== 'wallet'"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                <option value="">{{ __('اختر النوع') }}</option>
                                @foreach ($walletTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                            @error('wallet_type_id')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label for="acc-wallet-phone" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('رقم هاتف المحفظة') }}</label>
                        <input id="acc-wallet-phone" name="wallet_phone" x-model="form.wallet_phone" dir="ltr" :disabled="kind !== 'wallet'" placeholder="0595817016"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        @error('wallet_phone')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- ═══ cash fields ═══ --}}
                <div class="space-y-4" x-show="kind === 'cash'" x-cloak>
                    <div class="space-y-1.5">
                        <label for="acc-cash-box" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الصندوق النقدي') }}</label>
                        <select id="acc-cash-box" name="cash_box_id" x-model="form.cash_box_id" @change="onInstitutionChange()" :disabled="kind !== 'cash'"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            <option value="">{{ __('اختر الصندوق') }}</option>
                            @foreach ($cashBoxes as $box)
                                <option value="{{ $box->id }}">{{ $box->name }}</option>
                            @endforeach
                        </select>
                        @error('cash_box_id')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- ═══ shared fields ═══ --}}
                <div class="space-y-1.5">
                    <label for="acc-holder" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اسم صاحب الحساب') }}</label>
                    <input id="acc-holder" name="holder_name" value="{{ old('holder_name') }}" required placeholder="{{ __('مثال: أحمد محمد') }}"
                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    @error('holder_name')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                </div>

                <div class="space-y-1.5">
                    <label for="acc-currency" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('العملة') }}</label>
                    <select id="acc-currency" name="currency_id" x-model="form.currency_id" required
                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                        <option value="">{{ __('اختر البنك/المحفظة أولاً') }}</option>
                        <template x-for="c in supportedCurrencies()" :key="c.id">
                            <option :value="c.id" x-text="c.name + ' (' + c.symbol + ')'"></option>
                        </template>
                    </select>
                    <p class="font-body-sm text-body-sm text-outline" x-show="supportedCurrencies().length === 0" x-cloak>{{ __('اختر بنكاً أو محفظة تدعم عملة واحدة على الأقل.') }}</p>
                    @error('currency_id')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-1.5">
                        <label for="acc-phone" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('رقم الهاتف (اختياري)') }}</label>
                        <input id="acc-phone" name="phone" value="{{ old('phone') }}" dir="ltr" placeholder="0595817016"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        @error('phone')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-1.5">
                        <label for="acc-whatsapp" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('رقم واتساب (اختياري)') }}</label>
                        <input id="acc-whatsapp" name="whatsapp_phone" value="{{ old('whatsapp_phone') }}" dir="ltr" placeholder="+972595817016"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        @error('whatsapp_phone')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label for="acc-opening" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الرصيد الافتتاحي (اختياري)') }}</label>
                    <input id="acc-opening" name="opening_balance" value="{{ old('opening_balance') }}" type="number" step="0.01" dir="ltr" placeholder="0.00"
                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    <p class="font-body-sm text-body-sm text-outline">{{ __('يُسجَّل كأول حركة في الدفتر. موجب = مدين، سالب = دائن.') }}</p>
                    @error('opening_balance')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                </div>
            </section>

            <div class="flex justify-end gap-3">
                <a href="{{ route('accounts.index') }}" class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container" title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</a>
                <button type="submit" :disabled="submitting"
                    class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                    x-text="submitting ? '{{ __('جارٍ الحفظ...') }}' : '{{ __('إنشاء الحساب') }}'"></button>
            </div>
        </form>
    </div>
@endsection
