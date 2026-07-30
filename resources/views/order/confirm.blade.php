@php
    // Public, login-free confirmation page - styled with the app's own design
    // system (same fonts, colours and Material tokens as layouts/guest) so it
    // reads as part of حوالاتي, not a bolted-on page. Bilingual via ?lang.
    $t = fn (string $ar, string $en) => $lang === 'en' ? $en : $ar;
    $rtl = $lang !== 'en';
    $money = fn ($v) => number_format((float) $v, 2) . ' ₪';
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&family=Cairo:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block">

    {{-- Reveals the icons only once the ligature font is confirmed - same guard as layouts/guest. --}}
    <script>
        (function () {
            var html = document.documentElement;
            var face = '24px "Material Symbols Outlined"';
            var remember = function () { try { sessionStorage.setItem('iconsReady', '1'); } catch (e) {} };
            var reveal = function () { html.classList.add('icons-ready'); };
            try { if (sessionStorage.getItem('iconsReady') === '1') { reveal(); return; } } catch (e) {}
            if (!document.fonts || !document.fonts.load) { reveal(); return; }
            if (document.fonts.check(face)) { reveal(); remember(); return; }
            document.fonts.load(face).then(function () { if (document.fonts.check(face)) { reveal(); remember(); } });
        })();
    </script>
    <noscript><style>.material-symbols-outlined{visibility:visible}</style></noscript>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface font-sans text-on-surface antialiased">
    <div class="flex min-h-screen flex-col">
        {{-- Brand bar, mirroring layouts/guest --}}
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
                    {{-- Card head --}}
                    <div class="flex items-center gap-4 bg-primary px-6 py-6 text-on-primary">
                        <span class="material-symbols-outlined flex-shrink-0 !text-4xl" style="font-variation-settings:'FILL' 1">receipt_long</span>
                        <div>
                            <h1 class="text-xl font-bold leading-tight">{{ $t('تأكيد استلام حوالة بنكية', 'Bank transfer confirmation') }}</h1>
                            <p class="mt-1 text-sm opacity-90">{{ $t('طلب', 'Order') }} <span dir="ltr">{{ $orderNo }}</span></p>
                        </div>
                    </div>

                    <div class="p-6">
                        {{-- Details --}}
                        <dl class="rounded-2xl border border-outline-variant/70 bg-surface">
                            @php
                                $rows = array_filter([
                                    [$t('التاريخ', 'Date'), $num(($transfer->created_at ?? now())->format('Y-m-d H:i')), true],
                                    [$t('العميل', 'Customer'), e($transfer->customer), false],
                                    filled($phone) ? [$t('هاتف العميل', 'Customer phone'), $num($phone), true] : null,
                                    $transfer->bank ? [$t('البنك / المحفظة', 'Bank / Wallet'), e($transfer->bank->name), false] : null,
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

                        {{-- Items --}}
                        @php
                            $items = ($transfer->relationLoaded('lineItems') && $transfer->lineItems->isNotEmpty())
                                ? $transfer->lineItems
                                : null;
                        @endphp
                        <div class="mt-4 rounded-2xl bg-surface-container-low p-4">
                            <h2 class="mb-2 text-xs font-bold uppercase tracking-wide text-on-surface-variant">{{ $t('الأصناف', 'Items') }}</h2>
                            @if ($items)
                                <ul class="space-y-1.5">
                                    @foreach ($items as $line)
                                        <li class="flex items-center justify-between gap-3 text-sm">
                                            <span class="text-on-surface">{{ $line->name }} <span class="text-on-surface-variant">×<span dir="ltr">{{ $line->quantity }}</span></span></span>
                                            <span dir="ltr" class="font-medium text-on-surface">{{ $money($line->quantity * (float) $line->price) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-sm text-on-surface">{{ $transfer->item_name }}</p>
                            @endif
                            <div class="mt-3 flex items-center justify-between border-t border-outline-variant/60 pt-3">
                                <span class="font-bold text-on-surface">{{ $t('الإجمالي', 'Total') }}</span>
                                <span dir="ltr" class="text-lg font-extrabold text-primary">{{ $money($transfer->amount) }}</span>
                            </div>
                        </div>

                        {{-- Action / outcome --}}
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
                            <form method="POST" action="{{ route('order.confirm.act', ['license' => $license, 'token' => $token]) }}" class="mt-4 grid gap-3">
                                @csrf
                                <input type="hidden" name="lang" value="{{ $lang }}">
                                <button type="submit" name="action" value="confirm"
                                    class="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary px-6 py-4 text-base font-bold text-on-primary shadow-sm transition-transform active:scale-[.99]">
                                    <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1">check_circle</span>
                                    {{ $t('نعم، تم استلام الحوالة', 'Yes, transfer received') }}
                                </button>
                                <button type="submit" name="action" value="reject"
                                    class="flex w-full items-center justify-center gap-2 rounded-2xl border border-error bg-surface px-6 py-4 text-base font-bold text-error transition-colors hover:bg-error-container/40">
                                    <span class="material-symbols-outlined">cancel</span>
                                    {{ $t('لم تصل الحوالة', 'Transfer not received') }}
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
