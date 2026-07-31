{{--
    The little screen mock inside each product-tour panel.

    Split out of welcome.blade.php because four inline mocks buried the page's
    structure under ~150 lines of decorative markup.

    Drawn with the app's own tokens rather than screenshots, so it themes with
    the page, reads correctly in RTL and LTR, costs no image bytes, and cannot
    go stale the way a captured screenshot does. Everything here is decorative:
    the whole block is aria-hidden, and every figure is illustrative.

    @param string $panel  one of: orders, accounts, reports, team
--}}

<div class="rounded-2xl border border-outline-variant/50 bg-surface-container-low p-5" aria-hidden="true">
    @if ($panel === 'orders')
        <div class="flex items-center justify-between">
            <span class="font-body-sm font-bold text-on-surface">{{ __('طلب جديد') }}</span>
            <span class="rounded-full bg-primary/10 px-3 py-1 font-numeral-lg text-[11px] font-bold text-primary" dir="ltr">#WBPK</span>
        </div>

        <div class="mt-4 space-y-2">
            @foreach ([['lunch_dining', 2, '30.00'], ['local_cafe', 1, '8.00'], ['nutrition', 3, '47.00']] as $i => [$icon, $qty, $amount])
                <div class="tick-in flex items-center gap-3 rounded-xl border border-outline-variant/50 bg-surface-container-lowest p-2.5" style="animation-delay: {{ 80 + $i * 110 }}ms">
                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"><span class="material-symbols-outlined !text-base">{{ $icon }}</span></span>
                    <span class="h-2 flex-1 rounded-full bg-outline-variant/50"></span>
                    <span class="font-numeral-lg text-xs font-bold text-outline" dir="ltr">×{{ $qty }}</span>
                    <span class="font-numeral-lg text-xs font-bold text-on-surface" dir="ltr">{{ $amount }} ₪</span>
                </div>
            @endforeach
        </div>

        <div class="mt-4 flex items-center justify-between rounded-xl bg-primary p-3.5 text-on-primary">
            <span class="font-body-sm font-bold">{{ __('الإجمالي') }}</span>
            <span class="font-display-lg text-xl font-bold" dir="ltr">85.00 ₪</span>
        </div>

    @elseif ($panel === 'accounts')
        <div class="grid gap-2.5 sm:grid-cols-2">
            @foreach ([
                ['account_balance', __('بنك القدس'), '12,480.00', 'text-primary bg-primary/10'],
                ['account_balance_wallet', __('محفظة جوّال'), '3,120.50', 'text-secondary bg-secondary-container/15'],
                ['payments', __('صندوق النقد'), '2,640.00', 'text-primary bg-primary/10'],
                ['savings', __('صندوق الفرع'), '1,890.00', 'text-tertiary bg-tertiary/10'],
            ] as $i => [$icon, $label, $amount, $accent])
                <div class="tick-in rounded-xl border border-outline-variant/50 bg-surface-container-lowest p-3.5" style="animation-delay: {{ 80 + $i * 90 }}ms">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg {{ $accent }}"><span class="material-symbols-outlined !text-lg">{{ $icon }}</span></span>
                    <p class="mt-2.5 truncate font-body-sm text-on-surface-variant">{{ $label }}</p>
                    <p class="mt-0.5 font-numeral-lg text-lg font-bold text-on-surface" dir="ltr">{{ $amount }} ₪</p>
                </div>
            @endforeach
        </div>

        <div class="mt-3 flex items-center gap-2 rounded-xl border border-success/30 bg-success-container/10 p-3">
            <span class="material-symbols-outlined !text-lg text-success" data-weight="fill">verified</span>
            <span class="font-body-sm font-bold text-on-surface">{{ __('كل الحسابات مطابقة') }}</span>
        </div>

    @elseif ($panel === 'reports')
        <div class="flex items-start justify-between">
            <div>
                <p class="font-body-sm text-on-surface-variant">{{ __('صافي الربح هذا الشهر') }}</p>
                <p class="mt-1 font-display-lg text-3xl font-bold text-on-surface" dir="ltr">6,412.00 ₪</p>
            </div>
            <span class="flex items-center gap-1 rounded-full bg-success-container/15 px-3 py-1 font-body-sm font-bold text-success">
                <span class="material-symbols-outlined !text-base">trending_up</span><span dir="ltr">+24%</span>
            </span>
        </div>

        <div class="mt-5 flex h-32 items-end gap-1.5">
            @foreach ([35, 52, 44, 70, 58, 82, 66, 95, 74, 88, 61, 79] as $i => $h)
                <div class="grow-bar w-full rounded-t-md {{ $h >= 88 ? 'bg-secondary-container' : 'bg-primary-container opacity-45' }}" style="height: {{ $h }}%; --d: {{ 100 + $i * 45 }}ms"></div>
            @endforeach
        </div>

        <div class="mt-4 grid grid-cols-2 gap-2.5">
            @foreach ([[__('الإيرادات'), '18,240.00', 'text-primary'], [__('المصروفات'), '11,828.00', 'text-outline']] as [$label, $amount, $tone])
                <div class="rounded-xl border border-outline-variant/50 bg-surface-container-lowest p-3">
                    <p class="font-body-sm text-on-surface-variant">{{ $label }}</p>
                    <p class="mt-0.5 font-numeral-lg text-base font-bold {{ $tone }}" dir="ltr">{{ $amount }} ₪</p>
                </div>
            @endforeach
        </div>

    @else
        <div class="space-y-2.5">
            @foreach ([
                [__('مالك'), 'shield_person', __('كل الصلاحيات'), 4, 'bg-primary text-on-primary'],
                [__('مدير'), 'badge', __('كل شيء عدا الفوترة'), 3, 'bg-secondary-container text-on-secondary-fixed'],
                [__('محاسب'), 'assessment', __('تقارير ومصروفات'), 2, 'bg-tertiary/20 text-tertiary'],
                [__('موظف'), 'person', __('الطلبات فقط'), 1, 'bg-surface-container-high text-on-surface-variant'],
            ] as $i => [$role, $icon, $scope, $filled, $badge])
                <div class="tick-in flex items-center gap-3 rounded-xl border border-outline-variant/50 bg-surface-container-lowest p-3" style="animation-delay: {{ 80 + $i * 100 }}ms">
                    <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg {{ $badge }}"><span class="material-symbols-outlined !text-lg">{{ $icon }}</span></span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-body-sm font-bold text-on-surface">{{ $role }}</span>
                        <span class="block truncate font-body-sm text-outline">{{ $scope }}</span>
                    </span>
                    {{-- Four pips standing in for permission scope: full, then
                         progressively fewer, which reads at a glance without
                         inventing a number the product does not publish. --}}
                    <span class="flex flex-shrink-0 items-center gap-1">
                        @for ($pip = 1; $pip <= 4; $pip++)
                            <span class="h-1.5 w-1.5 rounded-full {{ $pip <= $filled ? 'bg-primary' : 'bg-outline-variant' }}"></span>
                        @endfor
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</div>
