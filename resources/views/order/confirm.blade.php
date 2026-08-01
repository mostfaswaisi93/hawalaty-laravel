@php

    $t = fn (string $ar, string $en) => $lang === 'en' ? $en : $ar;
    $rtl = $lang !== 'en';
    $symbol = $transfer->account?->currency?->symbol ?? '₪';
    $money = fn ($v) => number_format((float) $v, 2).' '.$symbol;
    $orderNo = '#' . strtoupper(substr((string) $transfer->id, -8));
    $phone = $transfer->customerProfile?->phone;
    $decided = $transfer->confirmationDecided();
    $confirmed = $transfer->confirmation_action === 'confirmed';
    $otherLang = $lang === 'en' ? 'ar' : 'en';
    $num = fn ($v) => '<span dir="ltr" class="inline-block">' . e($v) . '</span>';
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}" class="lang-{{ $lang }}"
    x-data="{ dark: localStorage.getItem('theme') === 'dark' }"
    x-init="$watch('dark', v => { document.documentElement.classList.toggle('dark', v); localStorage.setItem('theme', v ? 'dark' : 'light') }); document.documentElement.classList.toggle('dark', dark)"
    :class="{ dark: dark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $t('تأكيد استلام حوالة — حوالاتي', 'Transfer Confirmation — Hawalaty') }}</title>

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="apple-touch-icon" href="/favicon.svg">
    <x-fonts />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface font-sans text-on-surface antialiased">
    <div class="flex min-h-screen flex-col">
        <header class="flex h-16 flex-shrink-0 items-center justify-between border-b border-outline-variant bg-surface px-4 md:px-8">
            <div class="flex items-center gap-3">
                <x-logo />
                <span class="text-2xl font-bold leading-none text-primary">{{ __('حوالاتي') }}</span>
            </div>
            <div class="flex items-center gap-1">
                <a href="{{ route('order.confirm', ['license' => $license, 'token' => $token, 'lang' => $otherLang]) }}"
                    class="flex h-10 min-w-10 items-center justify-center rounded-full px-3 font-bold text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                    aria-label="{{ $t('تغيير اللغة', 'Change language') }}">
                    {{ $otherLang === 'en' ? 'EN' : 'ع' }}
                </a>
                <button type="button" @click="dark = !dark"
                    class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                    :aria-label="dark ? '{{ $t('الوضع الفاتح', 'Light mode') }}' : '{{ $t('الوضع الداكن', 'Dark mode') }}'">
                    <span class="material-symbols-outlined" x-text="dark ? 'light_mode' : 'dark_mode'">dark_mode</span>
                </button>
            </div>
        </header>

        <main class="flex flex-grow items-start justify-center px-4 py-8 md:py-12">
            <div class="w-full max-w-xl">
                <div class="overflow-hidden rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-sm">
                    <div class="flex items-center gap-4 bg-primary px-6 py-6 text-on-primary">
                        <span class="material-symbols-outlined flex-shrink-0 !text-4xl" style="font-variation-settings:'FILL' 1">receipt_long</span>
                        <div>
                            <h1 class="text-xl font-bold leading-tight">{{ $t('تأكيد استلام حوالة بنكية', 'Bank transfer confirmation') }}</h1>
                            <p class="mt-1 text-sm opacity-90">{{ $t('طلب', 'Order') }} <span dir="ltr">{{ $orderNo }}</span></p>
                        </div>
                    </div>

                    <div class="p-6">
                        <dl class="rounded-2xl border border-outline-variant/70 bg-surface">
                            @php
                                $rows = array_filter([
                                    [$t('التاريخ', 'Date'), $num(($transfer->created_at ?? now())->format('Y-m-d H:i')), true],
                                    [$t('العميل', 'Customer'), e($transfer->customer), false],
                                    filled($phone) ? [$t('هاتف العميل', 'Customer phone'), $num($phone), true] : null,
                                    $transfer->account ? [$t('الحساب', 'Account'), e($transfer->account->label()), false] : null,
                                    filled($transfer->reference_number) ? [$t('الرقم المرجعي', 'Reference number'), $num($transfer->reference_number), true] : null,
                                    filled($transfer->transferred_amount) ? [$t('المبلغ المُحوَّل', 'Transferred amount'), $num($money($transfer->transferred_amount)), true] : null,
                                ]);
                            @endphp
                            @foreach ($rows as $i => $row)
                                <div class="flex items-center justify-between gap-4 px-4 py-3.5 @if (! $loop->last) border-b border-outline-variant/60 @endif">
                                    <dt class="text-sm text-on-surface-variant">{{ $row[0] }}</dt>
                                    <dd class="text-end font-semibold text-on-surface">{!! $row[1] !!}</dd>
                                </div>
                            @endforeach
                        </dl>

                        @php
                            $items = ($transfer->relationLoaded('lineItems') && $transfer->lineItems->isNotEmpty())
                                ? $transfer->lineItems
                                : null;
                        @endphp
                        <div class="mt-4 rounded-2xl bg-surface-container-low p-4">
                            <h2 class="mb-2 text-xs font-bold uppercase tracking-wide text-on-surface-variant">{{ $t('الأصناف', 'Items') }}</h2>
                            @if ($items)
                                <div class="overflow-x-auto rounded-xl border border-outline-variant/70 bg-surface">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="bg-surface-container text-xs text-on-surface-variant">
                                                <th class="px-3 py-2 text-start font-bold">{{ $t('الصنف', 'Item') }}</th>
                                                <th class="w-16 px-2 py-2 text-center font-bold">{{ $t('كمية', 'Qty') }}</th>
                                                <th class="w-24 px-2 py-2 text-end font-bold">{{ $t('السعر', 'Price') }}</th>
                                                <th class="w-28 px-3 py-2 text-end font-bold">{{ $t('المجموع', 'Total') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-outline-variant/50">
                                            @foreach ($items as $line)
                                                <tr>
                                                    <td class="px-3 py-2 text-on-surface">{{ $line->name }}</td>
                                                    <td class="px-2 py-2 text-center text-on-surface-variant" dir="ltr">{{ $line->quantity }}</td>
                                                    <td class="px-2 py-2 text-end text-on-surface-variant" dir="ltr">{{ $money($line->price) }}</td>
                                                    <td class="px-3 py-2 text-end font-bold text-on-surface" dir="ltr">{{ $money($line->quantity * (float) $line->price) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-sm text-on-surface">{{ $transfer->item_name }}</p>
                            @endif
                            @if ($transfer->discount > 0)
                                <div class="mt-3 space-y-1.5 border-t border-outline-variant/60 pt-3">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-on-surface-variant">{{ $t('المجموع الفرعي', 'Subtotal') }}</span>
                                        <span dir="ltr" class="font-medium text-on-surface">{{ $money($items ? $items->sum(fn ($line) => $line->quantity * (float) $line->price) : $transfer->amount + $transfer->discount) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-on-surface-variant">{{ $t('الخصم', 'Discount') }}</span>
                                        <span dir="ltr" class="font-medium text-error">− {{ $money($transfer->discount) }}</span>
                                    </div>
                                </div>
                                <div class="mt-2 flex items-center justify-between border-t border-outline-variant/60 pt-3">
                            @else
                                <div class="mt-3 flex items-center justify-between border-t border-outline-variant/60 pt-3">
                            @endif
                                <span class="font-bold text-on-surface">{{ $t('الإجمالي', 'Total') }}</span>
                                <span dir="ltr" class="text-lg font-extrabold text-primary">{{ $money($transfer->amount) }}</span>
                            </div>
                        </div>

                        @if ($decided)
                            <div class="mt-6 flex flex-col items-center rounded-2xl p-6 text-center {{ $confirmed ? 'bg-success-container' : 'bg-error-container' }}">
                                <span class="material-symbols-outlined !text-5xl {{ $confirmed ? 'text-success' : 'text-error' }}" style="font-variation-settings:'FILL' 1">
                                    {{ $confirmed ? 'check_circle' : 'cancel' }}
                                </span>
                                <p class="mt-2 text-lg font-bold {{ $confirmed ? 'text-success' : 'text-error' }}">
                                    {{ $confirmed
                                        ? $t('تم تأكيد استلام الحوالة', 'Transfer receipt confirmed')
                                        : $t('تم الإبلاغ بعدم وصول الحوالة', 'Reported as not received') }}
                                </p>
                                <p class="mt-1 text-sm text-on-surface-variant">
                                    {{ $t('تم تسجيل هذا الرد مسبقاً بتاريخ', 'This response was already recorded on') }}
                                    <span dir="ltr">{{ $transfer->confirmed_at?->format('Y-m-d H:i') }}</span>
                                </p>
                            </div>
                        @else
                            <p class="mt-6 text-center text-sm text-on-surface-variant">
                                {{ $t('يرجى تأكيد استلامك لهذه الحوالة في حسابك، أو الإبلاغ عن عدم وصولها.',
                                      'Please confirm you received this transfer in your account, or report that it did not arrive.') }}
                            </p>
                            <form method="POST" action="{{ route('order.confirm.act', ['license' => $license, 'token' => $token]) }}" class="mt-4 grid gap-3"
                                x-data="{ pending: null }">
                                @csrf
                                <input type="hidden" name="lang" value="{{ $lang }}">
                                <button type="submit" name="action" value="confirm"
                                    @click="pending = 'confirm'"
                                    :disabled="pending === 'reject'"
                                    :class="pending && 'pointer-events-none opacity-60'"
                                    class="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary px-6 py-4 text-base font-bold text-on-primary shadow-sm transition-transform active:scale-[.99]">
                                    <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1"
                                        :class="pending === 'confirm' && 'animate-spin'"
                                        x-text="pending === 'confirm' ? 'progress_activity' : 'check_circle'">check_circle</span>
                                    <span x-text="pending === 'confirm' ? @js($t('جارٍ التأكيد...', 'Confirming...')) : @js($t('نعم، تم استلام الحوالة', 'Yes, transfer received'))">{{ $t('نعم، تم استلام الحوالة', 'Yes, transfer received') }}</span>
                                </button>
                                <button type="submit" name="action" value="reject"
                                    @click="pending = 'reject'"
                                    :disabled="pending === 'confirm'"
                                    :class="pending && 'pointer-events-none opacity-60'"
                                    class="flex w-full items-center justify-center gap-2 rounded-2xl border border-error bg-surface px-6 py-4 text-base font-bold text-error transition-colors hover:bg-error-container/40">
                                    <span class="material-symbols-outlined" :class="pending === 'reject' && 'animate-spin'"
                                        x-text="pending === 'reject' ? 'progress_activity' : 'cancel'">cancel</span>
                                    <span x-text="pending === 'reject' ? @js($t('جارٍ الإرسال...', 'Submitting...')) : @js($t('لم تصل الحوالة', 'Transfer not received'))">{{ $t('لم تصل الحوالة', 'Transfer not received') }}</span>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <p class="mt-6 text-center text-xs text-on-surface-variant">
                    {{ $t('هذه رسالة تلقائية من نظام حوالاتي.', 'This is an automated message from the Hawalaty system.') }}
                </p>
            </div>
        </main>
    </div>
</body>
</html>
