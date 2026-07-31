@extends('layouts.app')

@section('title', __('تعديل الحساب - حوالاتي'))

@php
    use App\Enums\AccountKind;
    // Only institutions that support this account's (fixed) currency may be
    // chosen - the request enforces the same, this just hides the rest.
    $bankOptions = $banks->filter(fn ($b) => $b->currencies->contains('id', $account->currency_id));
    $walletOptions = $wallets->filter(fn ($w) => $w->currencies->contains('id', $account->currency_id));
    $isBank = $account->kind === AccountKind::Bank;
@endphp

@section('content')
    <div class="page-enter mx-auto max-w-2xl space-y-6" x-data="{ submitting: false }">
        <header class="flex items-center gap-3">
            <a href="{{ route('accounts.show', $account->id) }}" class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('رجوع') }}" title="{{ __('رجوع') }}">
                <span class="material-symbols-outlined rtl:-scale-x-100">arrow_back</span>
            </a>
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('تعديل الحساب') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ $account->kind->label() }} · {{ $account->currency?->name }} <span dir="ltr">({{ $account->currency?->symbol }})</span></p>
            </div>
        </header>

        <form method="POST" action="{{ route('accounts.update', $account->id) }}" class="space-y-6" @submit="submitting = true">
            @csrf
            @method('PUT')

            <section class="tonal-elevation space-y-4 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                @if ($isBank)
                    <div class="space-y-1.5">
                        <label for="acc-bank" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('البنك') }}</label>
                        <select id="acc-bank" name="bank_name_id" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            @foreach ($bankOptions as $bank)
                                <option value="{{ $bank->id }}" @selected(old('bank_name_id', $account->bank_name_id) === $bank->id)>{{ $bank->name }}</option>
                            @endforeach
                        </select>
                        @error('bank_name_id')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-1.5">
                            <label for="acc-account-type" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('نوع الحساب') }}</label>
                            <select id="acc-account-type" name="account_type_id" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                @foreach ($accountTypes as $type)
                                    <option value="{{ $type->id }}" @selected(old('account_type_id', $account->account_type_id) === $type->id)>{{ $type->name }}</option>
                                @endforeach
                            </select>
                            @error('account_type_id')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="space-y-1.5">
                            <label for="acc-iban" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('آيبان (اختياري)') }}</label>
                            <input id="acc-iban" name="iban" value="{{ old('iban', $account->iban) }}" dir="ltr" placeholder="PS00 0000 ..."
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            @error('iban')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                @else
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-1.5">
                            <label for="acc-wallet" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المحفظة') }}</label>
                            <select id="acc-wallet" name="wallet_name_id" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                @foreach ($walletOptions as $wallet)
                                    <option value="{{ $wallet->id }}" @selected(old('wallet_name_id', $account->wallet_name_id) === $wallet->id)>{{ $wallet->name }}</option>
                                @endforeach
                            </select>
                            @error('wallet_name_id')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="space-y-1.5">
                            <label for="acc-wallet-type" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('نوع المحفظة') }}</label>
                            <select id="acc-wallet-type" name="wallet_type_id" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                @foreach ($walletTypes as $type)
                                    <option value="{{ $type->id }}" @selected(old('wallet_type_id', $account->wallet_type_id) === $type->id)>{{ $type->name }}</option>
                                @endforeach
                            </select>
                            @error('wallet_type_id')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label for="acc-wallet-phone" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('رقم هاتف المحفظة') }}</label>
                        <input id="acc-wallet-phone" name="wallet_phone" value="{{ old('wallet_phone', $account->wallet_phone) }}" dir="ltr" placeholder="0595817016"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        @error('wallet_phone')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                @endif

                <div class="space-y-1.5">
                    <label for="acc-holder" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اسم صاحب الحساب') }}</label>
                    <input id="acc-holder" name="holder_name" value="{{ old('holder_name', $account->holder_name) }}" required
                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    @error('holder_name')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-1.5">
                        <label for="acc-phone" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('رقم الهاتف (اختياري)') }}</label>
                        <input id="acc-phone" name="phone" value="{{ old('phone', $account->phone) }}" dir="ltr" placeholder="0595817016"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        @error('phone')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-1.5">
                        <label for="acc-whatsapp" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('رقم واتساب (اختياري)') }}</label>
                        <input id="acc-whatsapp" name="whatsapp_phone" value="{{ old('whatsapp_phone', $account->whatsapp_phone) }}" dir="ltr" placeholder="+972595817016"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        @error('whatsapp_phone')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <input type="hidden" name="active" value="0">
                <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3">
                    <span class="font-body-sm font-bold text-on-surface">{{ __('نشط') }}</span>
                    <input type="checkbox" name="active" value="1" @checked(old('active', $account->active)) class="h-5 w-5 rounded accent-primary" />
                </label>
            </section>

            <div class="flex justify-end gap-3">
                <a href="{{ route('accounts.show', $account->id) }}" class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container" title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</a>
                <button type="submit" :disabled="submitting"
                    class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                    x-text="submitting ? '{{ __('جارٍ الحفظ...') }}' : '{{ __('حفظ التغييرات') }}'"></button>
            </div>
        </form>
    </div>
@endsection
