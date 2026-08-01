@extends('layouts.admin')

@section('title', __('المقاصة - حوالاتي'))

@section('content')
    {{--
        The clearing board. Everything above the history table is the LIVE,
        uncommitted position for the period that is currently open - nothing
        here is stored until "تنفيذ المقاصة" is submitted.
    --}}
    @php
        $p = $preview;
        $overAllocated = $p['percentageTotal'] > 100;
        $underAllocated = $p['percentageTotal'] < 100;
    @endphp

    <div class="page-enter space-y-8"
        x-data="{
            runOpen: {{ $errors->any() ? 'true' : 'false' }},
            submitting: false,
        }">

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('المقاصة') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('تسوية حصص الشركاء: ما استحقّه كل شريك مقابل ما سحبه فعلياً.') }}</p>
            </div>
            {{-- Over-allocation disables the button, because SettlementService now
                 REFUSES the run outright. Leaving it clickable would walk the
                 admin into an error they can already see explained above. --}}
            <button type="button" @click="runOpen = true; submitting = false"
                @disabled($p['hasOpenDraft'] || empty($p['lines']) || $overAllocated)
                class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                title="{{ $p['hasOpenDraft'] ? __('توجد مسودّة مفتوحة') : ($overAllocated ? __('مجموع نسب الشركاء يتجاوز 100%') : __('تنفيذ المقاصة')) }}">
                <span class="material-symbols-outlined !text-xl">balance</span>{{ __('تنفيذ المقاصة') }}
            </button>
        </header>

        {{-- Open period --}}
        <section class="reveal-up flex flex-col gap-3 rounded-3xl border border-outline-variant/50 bg-surface-container-low p-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <span class="material-symbols-outlined">event_repeat</span>
                </span>
                <div>
                    <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الفترة المفتوحة') }}</p>
                    <p class="mt-0.5 font-body-lg font-bold text-on-surface">
                        <span dir="ltr">{{ $p['periodFrom']->translatedFormat('d M Y') }} - {{ $p['periodTo']->translatedFormat('d M Y') }}</span>
                    </p>
                </div>
            </div>
            {{-- A LINK, not a badge. The open draft is the only thing that
                 disables "تنفيذ المقاصة", so announcing it without a way to
                 reach it left the page's primary action stuck behind a message
                 and made the admin hunt for the draft in the history table. --}}
            @if ($p['openDraft'])
                <a href="{{ route('admin.settlements.show', $p['openDraft']) }}"
                    class="inline-flex items-center gap-1.5 self-start rounded-full bg-secondary-container/20 px-3 py-1.5 font-body-sm text-body-sm font-bold text-secondary transition-colors hover:bg-secondary-container/40 sm:self-auto"
                    title="{{ __('فتح المسودّة') }}">
                    <span class="material-symbols-outlined !text-base">edit_note</span>
                    {{ __('توجد مسودّة بانتظار التأكيد') }}
                    <span dir="ltr" class="font-numeral-lg">{{ $p['openDraft']->reference }}</span>
                    <span class="material-symbols-outlined !text-base rtl:-scale-x-100">chevron_right</span>
                </a>
            @endif
        </section>

        {{-- Headline figures --}}
        <section class="stagger grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-card>
                <p class="font-body-sm text-on-surface-variant">{{ __('المدفوعات في الفترة') }}</p>
                <p class="mt-2 font-numeral-lg text-numeral-lg font-bold text-primary"><span dir="ltr">{{ number_format($p['totalPayments'], 2) }} ₪</span></p>
            </x-card>
            <x-card>
                <p class="font-body-sm text-on-surface-variant">{{ __('المصروفات في الفترة') }}</p>
                <p class="mt-2 font-numeral-lg text-numeral-lg font-bold text-error"><span dir="ltr">{{ number_format($p['totalExpenses'], 2) }} ₪</span></p>
            </x-card>
            <x-card>
                <p class="font-body-sm text-on-surface-variant">{{ __('الربح القابل للتوزيع') }}</p>
                <p class="mt-2 font-numeral-lg text-numeral-lg font-bold {{ $p['distributableProfit'] >= 0 ? 'text-success' : 'text-error' }}">
                    <span dir="ltr">{{ number_format($p['distributableProfit'], 2) }} ₪</span>
                </p>
                <p class="mt-1 font-body-sm text-body-sm text-outline">{{ __('المدفوعات - المصروفات') }}</p>
            </x-card>
            <x-card>
                <p class="font-body-sm text-on-surface-variant">{{ __('صافي المستحق للشركاء') }}</p>
                <p class="mt-2 font-numeral-lg text-numeral-lg font-bold {{ $p['totalNet'] >= 0 ? 'text-success' : 'text-error' }}">
                    <span dir="ltr">{{ number_format($p['totalNet'], 2) }} ₪</span>
                </p>
                <p class="mt-1 font-body-sm text-body-sm text-outline">{{ __('بعد خصم المسحوبات') }}</p>
            </x-card>
        </section>

        @if ($overAllocated)
            <div class="reveal-up flex items-start gap-3 rounded-2xl border border-error/30 bg-error-container/40 p-4 text-on-error-container">
                <span class="material-symbols-outlined text-error">warning</span>
                <div class="font-body-sm text-body-sm">
                    <p class="font-bold">{{ __('مجموع نسب الشركاء يتجاوز 100%') }}</p>
                    <p class="mt-1">{{ __('المجموع الحالي') }} <span dir="ltr">{{ number_format($p['percentageTotal'], 2) }}%</span> - {{ __('لا يمكن تنفيذ المقاصة حتى تُصحَّح النسب، لأنها ستوزّع أكثر من الربح المتاح.') }}</p>
                    <a href="{{ route('admin.partners.index') }}" class="mt-2 inline-flex items-center gap-1 font-bold text-error hover:underline" title="{{ __('الشركاء') }}">
                        {{ __('تعديل نسب الشركاء') }}
                        <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_forward</span>
                    </a>
                </div>
            </div>
        @endif

        {{-- Partner positions --}}
        <x-card :title="__('مراكز الشركاء')" :subtitle="__('الاستحقاق مقابل المسحوب خلال الفترة المفتوحة')">
            @if (empty($p['lines']))
                <div class="flex flex-col items-center gap-3 py-14 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/60">handshake</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا يوجد شركاء بعد') }}</p>
                    <a href="{{ route('admin.partners.index') }}" class="font-body-sm font-bold text-primary hover:underline" title="{{ __('إضافة شريك') }}">{{ __('إضافة شريك') }}</a>
                </div>
            @else
                <x-table-responsive :headers="[__('الشريك'), __('النسبة'), __('الاستحقاق'), __('المسحوب'), __('الصافي')]">
                    <x-slot:table>
                        @foreach ($p['lines'] as $line)
                            <tr class="hover:bg-surface-container-low">
                                <td class="px-4 py-3 font-body-sm font-bold text-on-surface">{{ $line['partnerName'] }}</td>
                                <td class="px-4 py-3 font-body-sm text-on-surface-variant"><span dir="ltr">{{ number_format($line['percentage'], 2) }}%</span></td>
                                <td class="px-4 py-3 font-body-sm text-on-surface"><span dir="ltr">{{ number_format($line['entitlement'], 2) }} ₪</span></td>
                                <td class="px-4 py-3 font-body-sm text-on-surface-variant"><span dir="ltr">{{ number_format($line['drawn'], 2) }} ₪</span></td>
                                <td class="px-4 py-3">
                                    <x-settlement-net :net="$line['net']" />
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($p['lines'] as $line)
                            <div class="tonal-elevation rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-body-lg font-bold text-on-surface">{{ $line['partnerName'] }}</p>
                                    <span class="font-body-sm text-body-sm text-on-surface-variant"><span dir="ltr">{{ number_format($line['percentage'], 2) }}%</span></span>
                                </div>
                                <dl class="mt-3 grid grid-cols-2 gap-3 font-body-sm text-body-sm">
                                    <div>
                                        <dt class="text-outline">{{ __('الاستحقاق') }}</dt>
                                        <dd class="mt-0.5 font-bold text-on-surface"><span dir="ltr">{{ number_format($line['entitlement'], 2) }} ₪</span></dd>
                                    </div>
                                    <div>
                                        <dt class="text-outline">{{ __('المسحوب') }}</dt>
                                        <dd class="mt-0.5 font-bold text-on-surface-variant"><span dir="ltr">{{ number_format($line['drawn'], 2) }} ₪</span></dd>
                                    </div>
                                </dl>
                                <div class="mt-3 border-t border-outline-variant/60 pt-3">
                                    <x-settlement-net :net="$line['net']" />
                                </div>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                {{-- Reconciliation. The identity holds by construction in
                     SettlementService; showing it makes the page auditable
                     instead of asking the admin to trust it. --}}
                <div class="mt-6 rounded-2xl border border-outline-variant/60 bg-surface-container-low p-4">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined !text-xl {{ $p['reconciles'] ? 'text-success' : 'text-error' }}">
                            {{ $p['reconciles'] ? 'verified' : 'error' }}
                        </span>
                        <p class="font-body-sm text-body-sm font-bold text-on-surface">{{ __('التحقق الحسابي') }}</p>
                    </div>
                    <div class="mt-3 grid grid-cols-1 gap-3 font-body-sm text-body-sm sm:grid-cols-3">
                        <div class="flex items-center justify-between gap-2 sm:block">
                            <span class="text-on-surface-variant">{{ __('صافي المستحقات') }}</span>
                            <span class="font-bold text-on-surface sm:mt-1 sm:block"><span dir="ltr">{{ number_format($p['totalNet'], 2) }} ₪</span></span>
                        </div>
                        <div class="flex items-center justify-between gap-2 sm:block">
                            <span class="text-on-surface-variant">{{ __('المحتجز للمنصّة') }}</span>
                            <span class="font-bold text-on-surface sm:mt-1 sm:block"><span dir="ltr">{{ number_format($p['retained'], 2) }} ₪</span></span>
                        </div>
                        <div class="flex items-center justify-between gap-2 sm:block">
                            <span class="text-on-surface-variant">{{ __('الربح - المسحوبات') }}</span>
                            <span class="font-bold text-on-surface sm:mt-1 sm:block"><span dir="ltr">{{ number_format($p['distributableProfit'] - $p['totalDrawn'], 2) }} ₪</span></span>
                        </div>
                    </div>
                    @if ($underAllocated)
                        <p class="mt-3 border-t border-outline-variant/60 pt-3 font-body-sm text-body-sm text-outline">
                            {{ __('نسب الشركاء تساوي') }} <span dir="ltr">{{ number_format($p['percentageTotal'], 2) }}%</span> - {{ __('والباقي يبقى محتجزاً للمنصّة.') }}
                        </p>
                    @endif
                </div>
            @endif
        </x-card>

        {{-- History --}}
        <x-card :title="__('سجل المقاصات')" :subtitle="__('الدورات السابقة والفترات المُقفلة')">
            @if ($settlements->total() === 0)
                <div class="flex flex-col items-center gap-2 py-12 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/60">history</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لم تُنفَّذ أي مقاصة بعد') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[__('المرجع'), __('الفترة'), __('الربح القابل للتوزيع'), __('صافي المستحقات'), __('الحالة'), '']">
                    <x-slot:table>
                        @foreach ($settlements as $row)
                            <tr class="hover:bg-surface-container-low">
                                <td class="px-4 py-3 font-body-sm font-bold text-on-surface"><span dir="ltr">{{ $row->reference }}</span></td>
                                <td class="px-4 py-3 font-body-sm text-on-surface-variant">
                                    <span dir="ltr">{{ $row->period_from->translatedFormat('d M Y') }} - {{ $row->period_to->translatedFormat('d M Y') }}</span>
                                </td>
                                <td class="px-4 py-3 font-body-sm text-on-surface"><span dir="ltr">{{ number_format($row->distributable_profit, 2) }} ₪</span></td>
                                <td class="px-4 py-3 font-body-sm text-on-surface"><span dir="ltr">{{ number_format($row->total_net, 2) }} ₪</span></td>
                                <td class="px-4 py-3"><x-settlement-status :status="$row->status" /></td>
                                <td class="px-4 py-3 text-end">
                                    <a href="{{ route('admin.settlements.show', $row) }}"
                                        @mouseenter="showTipBelow($el, @js(__('عرض التفاصيل')))" @mouseleave="hideTip()"
                                        @focus="showTipBelow($el, @js(__('عرض التفاصيل')))" @blur="hideTip()"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                                        aria-label="{{ __('عرض التفاصيل') }}" title="{{ __('عرض التفاصيل') }}">
                                        <span class="material-symbols-outlined !text-xl rtl:-scale-x-100">chevron_right</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($settlements as $row)
                            <a href="{{ route('admin.settlements.show', $row) }}"
                                class="tonal-elevation block rounded-2xl border border-outline-variant bg-surface-container-lowest p-4 transition-colors hover:bg-surface-container-low"
                                title="{{ __('عرض التفاصيل') }}">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-body-lg font-bold text-on-surface" dir="ltr">{{ $row->reference }}</p>
                                    <x-settlement-status :status="$row->status" />
                                </div>
                                <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
                                    <span dir="ltr">{{ $row->period_from->translatedFormat('d M Y') }} - {{ $row->period_to->translatedFormat('d M Y') }}</span>
                                </p>
                                <dl class="mt-3 grid grid-cols-2 gap-3 font-body-sm text-body-sm">
                                    <div>
                                        <dt class="text-outline">{{ __('الربح القابل للتوزيع') }}</dt>
                                        <dd class="mt-0.5 font-bold text-on-surface"><span dir="ltr">{{ number_format($row->distributable_profit, 2) }} ₪</span></dd>
                                    </div>
                                    <div>
                                        <dt class="text-outline">{{ __('صافي المستحقات') }}</dt>
                                        <dd class="mt-0.5 font-bold text-on-surface"><span dir="ltr">{{ number_format($row->total_net, 2) }} ₪</span></dd>
                                    </div>
                                </dl>
                            </a>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$settlements" :perPage="$settlements->perPage()" />
            @endif
        </x-card>

        {{-- Run-clearing modal --}}
        <div x-show="runOpen" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
            <div class="fixed inset-0 bg-black/40" x-show="runOpen" x-transition.opacity></div>
            <div x-show="runOpen" @keydown.escape.window="runOpen = false" role="dialog" aria-modal="true" aria-labelledby="run-settlement-title"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto overflow-x-hidden rounded-3xl bg-surface-container-lowest shadow-2xl">
                <x-modal-close on-close="runOpen = false" />
                <h3 id="run-settlement-title" class="border-b border-outline-variant/60 py-4 ps-6 pe-14 font-headline-md text-headline-md">{{ __('تنفيذ المقاصة') }}</h3>
                <form method="POST" action="{{ route('admin.settlements.store') }}" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf

                    <div class="rounded-xl border border-outline-variant/60 bg-surface-container-low p-3 font-body-sm text-body-sm text-on-surface-variant">
                        {{ __('تبدأ الفترة من') }}
                        <span class="font-bold text-on-surface" dir="ltr">{{ $p['periodFrom']->translatedFormat('d M Y') }}</span>
                        - {{ __('يُحدَّد هذا التاريخ تلقائياً من آخر مقاصة مؤكّدة.') }}
                    </div>

                    <div class="space-y-1.5">
                        <label for="periodTo" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('حتى تاريخ') }}</label>
                        <input id="periodTo" type="date" name="periodTo" value="{{ old('periodTo', now()->toDateString()) }}" required dir="ltr"
                            max="{{ now()->toDateString() }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        @error('periodTo')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="notes" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('ملاحظات') }} <span class="text-outline">({{ __('اختياري') }})</span></label>
                        <textarea id="notes" name="notes" rows="3" maxlength="1000"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">{{ old('notes') }}</textarea>
                        @error('notes')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-outline-variant/60 bg-surface-container-low p-3">
                        <input type="checkbox" name="confirm" value="1" @checked(old('confirm')) class="mt-0.5 h-5 w-5 flex-shrink-0 rounded accent-primary" />
                        <span class="font-body-sm text-body-sm">
                            <span class="block font-bold text-on-surface">{{ __('إقفال الفترة مباشرة') }}</span>
                            <span class="mt-0.5 block text-on-surface-variant">{{ __('عند الإقفال لا يمكن التراجع، وتبدأ الفترة التالية من اليوم التالي.') }}</span>
                        </span>
                    </label>

                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="runOpen = false"
                            class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container"
                            title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting"
                            @mouseenter="showTipBelow($el, @js(__('تنفيذ')))" @mouseleave="hideTip()"
                            @focus="showTipBelow($el, @js(__('تنفيذ')))" @blur="hideTip()"
                            class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:opacity-50"
                            title="{{ __('تنفيذ') }}"
                            x-text="submitting ? '{{ __('جارٍ التنفيذ...') }}' : '{{ __('تنفيذ') }}'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
