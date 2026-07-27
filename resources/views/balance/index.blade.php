@extends('layouts.app')

@section('title', __('الموازنة المالية - حوالاتي'))

@section('content')
    @php $s = $summary; @endphp

    <div class="page-enter space-y-6">
        <header>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الموازنة المالية') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('ما دخل وما خرج خلال الفترة، وحركة كل حساب.') }}</p>
        </header>

        {{-- date range --}}
        <form method="GET" action="{{ route('balance.index') }}" x-data="{ submitting: false }" @submit="submitting = true"
            class="reveal-up tonal-elevation flex flex-col gap-4 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 sm:flex-row sm:items-end" style="--d: 40ms">
            <div class="flex-1 space-y-1.5">
                <label for="from" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('من تاريخ') }}</label>
                <input id="from" type="date" name="from" value="{{ $from }}" dir="ltr"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
            </div>
            <div class="flex-1 space-y-1.5">
                <label for="to" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('إلى تاريخ') }}</label>
                <input id="to" type="date" name="to" value="{{ $to }}" dir="ltr"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                @error('to')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" :disabled="submitting"
                class="press flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container disabled:opacity-50"
                title="{{ __('عرض') }}">
                <span class="material-symbols-outlined !text-xl" :class="submitting && 'animate-spin'"
                    x-text="submitting ? 'progress_activity' : 'query_stats'">query_stats</span>
                {{ __('عرض') }}
            </button>
        </form>

        {{-- headline --}}
        <section class="stagger grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-card>
                <div class="flex items-center justify-between">
                    <p class="font-body-sm text-on-surface-variant">{{ __('الداخل') }}</p>
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-success-container/20 text-success">
                        <span class="material-symbols-outlined !text-xl">call_received</span>
                    </span>
                </div>
                <p class="mt-2 font-numeral-lg text-numeral-lg font-bold text-success"><span dir="ltr">{{ number_format($s['totalIn'], 2) }} ₪</span></p>
                <p class="mt-1 font-body-sm text-body-sm text-outline">{{ trans_choice('{1} طلب مكتمل واحد|[2,*] :count طلبات مكتملة', $s['orderCount'], ['count' => number_format($s['orderCount'])]) }}</p>
            </x-card>

            <x-card>
                <div class="flex items-center justify-between">
                    <p class="font-body-sm text-on-surface-variant">{{ __('الخارج') }}</p>
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-error-container/40 text-error">
                        <span class="material-symbols-outlined !text-xl">call_made</span>
                    </span>
                </div>
                <p class="mt-2 font-numeral-lg text-numeral-lg font-bold text-error"><span dir="ltr">{{ number_format($s['totalOut'], 2) }} ₪</span></p>
                <p class="mt-1 font-body-sm text-body-sm text-outline">{{ trans_choice('{1} مصروف واحد|[2,*] :count مصروفات', $s['expenseCount'], ['count' => number_format($s['expenseCount'])]) }}</p>
            </x-card>

            <x-card>
                <div class="flex items-center justify-between">
                    <p class="font-body-sm text-on-surface-variant">{{ __('الصافي') }}</p>
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl {{ $s['net'] >= 0 ? 'bg-primary/10 text-primary' : 'bg-error-container/40 text-error' }}">
                        <span class="material-symbols-outlined !text-xl">{{ $s['net'] >= 0 ? 'trending_up' : 'trending_down' }}</span>
                    </span>
                </div>
                <p class="mt-2 font-numeral-lg text-numeral-lg font-bold {{ $s['net'] >= 0 ? 'text-primary' : 'text-error' }}">
                    <span dir="ltr">{{ number_format($s['net'], 2) }} ₪</span>
                </p>
                <p class="mt-1 font-body-sm text-body-sm text-outline">{{ __('الداخل - الخارج') }}</p>
            </x-card>
        </section>

        {{-- what the outflow is made of --}}
        <x-card :title="__('تركيب المصروفات')" :subtitle="__('الرواتب جزء من الخارج، وليست إضافة عليه')">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="flex items-center justify-between gap-3 rounded-2xl border border-outline-variant/50 bg-surface-container-low p-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-secondary-container/20 text-secondary">
                            <span class="material-symbols-outlined">payments</span>
                        </span>
                        <span class="font-body-sm font-bold text-on-surface">{{ __('الرواتب') }}</span>
                    </div>
                    <span class="font-body-lg font-bold text-on-surface"><span dir="ltr">{{ number_format($s['salaries'], 2) }} ₪</span></span>
                </div>
                <div class="flex items-center justify-between gap-3 rounded-2xl border border-outline-variant/50 bg-surface-container-low p-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <span class="material-symbols-outlined">receipt_long</span>
                        </span>
                        <span class="font-body-sm font-bold text-on-surface">{{ __('مصروفات أخرى') }}</span>
                    </div>
                    <span class="font-body-lg font-bold text-on-surface"><span dir="ltr">{{ number_format($s['otherExpenses'], 2) }} ₪</span></span>
                </div>
            </div>
        </x-card>

        {{-- per account --}}
        <x-card :title="__('حركة الحسابات')" :subtitle="__('ما دخل وما خرج من كل بنك أو محفظة')">
            @if (empty($accounts))
                <div class="flex flex-col items-center gap-2 py-12 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/60">account_balance</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا حركة في هذه الفترة') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[__('الحساب'), ['label' => __('الداخل'), 'align' => 'end'], ['label' => __('الخارج'), 'align' => 'end'], ['label' => __('الصافي'), 'align' => 'end']]">
                    <x-slot:table>
                        @foreach ($accounts as $a)
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl {{ $a['isCash'] ? 'bg-secondary-container/20 text-secondary' : 'bg-primary/10 text-primary' }}">
                                            <span class="material-symbols-outlined !text-lg">{{ $a['isCash'] ? 'payments' : 'account_balance' }}</span>
                                        </span>
                                        <span class="font-body-sm font-bold text-on-surface">{{ $a['name'] }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-body-sm text-success"><span dir="ltr">{{ number_format($a['in'], 2) }} ₪</span></td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-body-sm text-error"><span dir="ltr">{{ number_format($a['out'], 2) }} ₪</span></td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-bold {{ $a['net'] >= 0 ? 'text-on-surface' : 'text-error' }}"><span dir="ltr">{{ number_format($a['net'], 2) }} ₪</span></td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($accounts as $a)
                            <div class="tonal-elevation rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl {{ $a['isCash'] ? 'bg-secondary-container/20 text-secondary' : 'bg-primary/10 text-primary' }}">
                                        <span class="material-symbols-outlined !text-lg">{{ $a['isCash'] ? 'payments' : 'account_balance' }}</span>
                                    </span>
                                    <p class="font-body-lg font-bold text-on-surface">{{ $a['name'] }}</p>
                                </div>
                                <dl class="mt-3 grid grid-cols-3 gap-2 font-body-sm text-body-sm">
                                    <div>
                                        <dt class="text-outline">{{ __('الداخل') }}</dt>
                                        <dd class="mt-0.5 font-bold text-success"><span dir="ltr">{{ number_format($a['in'], 2) }}</span></dd>
                                    </div>
                                    <div>
                                        <dt class="text-outline">{{ __('الخارج') }}</dt>
                                        <dd class="mt-0.5 font-bold text-error"><span dir="ltr">{{ number_format($a['out'], 2) }}</span></dd>
                                    </div>
                                    <div>
                                        <dt class="text-outline">{{ __('الصافي') }}</dt>
                                        <dd class="mt-0.5 font-bold {{ $a['net'] >= 0 ? 'text-on-surface' : 'text-error' }}"><span dir="ltr">{{ number_format($a['net'], 2) }}</span></dd>
                                    </div>
                                </dl>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>
            @endif
        </x-card>

        {{-- figures that are deliberately OUTSIDE the balance --}}
        @if ($s['pending'] > 0 || $s['changeGiven'] > 0)
            <x-card :title="__('خارج الحساب')" :subtitle="__('أرقام معروضة للعلم ولم تُحتسب ضمن الصافي')">
                <div class="space-y-3">
                    @if ($s['pending'] > 0)
                        <div class="flex items-start gap-3 rounded-2xl border border-outline-variant/50 bg-surface-container-low p-4">
                            <span class="material-symbols-outlined text-secondary">hourglass_top</span>
                            <div class="min-w-0 flex-1">
                                <p class="font-body-sm font-bold text-on-surface">{{ __('طلبات قيد الانتظار') }} <span class="font-normal text-on-surface-variant" dir="ltr">{{ number_format($s['pending'], 2) }} ₪</span></p>
                                <p class="mt-0.5 font-body-sm text-body-sm text-outline">{{ __('لم تُحصَّل بعد، لذلك لا تدخل في الداخل.') }}</p>
                            </div>
                        </div>
                    @endif

                    @if ($s['changeGiven'] > 0)
                        <div class="flex items-start gap-3 rounded-2xl border border-outline-variant/50 bg-surface-container-low p-4">
                            <span class="material-symbols-outlined text-outline">currency_exchange</span>
                            <div class="min-w-0 flex-1">
                                <p class="font-body-sm font-bold text-on-surface">{{ __('مبالغ الفراطة') }} <span class="font-normal text-on-surface-variant" dir="ltr">{{ number_format($s['changeGiven'], 2) }} ₪</span></p>
                                <p class="mt-0.5 font-body-sm text-body-sm text-outline">{{ __('لم تُخصم من الصافي - أخبرنا إن كان يجب اعتبارها مبلغاً خارجاً.') }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </x-card>
        @endif
    </div>
@endsection
