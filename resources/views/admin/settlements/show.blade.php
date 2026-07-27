@extends('layouts.admin')

@section('title', $settlement->reference . ' - ' . __('المقاصة') . ' - ' . __('حوالاتي'))

@section('content')
    {{--
        A single clearing run, presented as the accounting document it is. A
        confirmed run has no edit path anywhere on this page by design - the
        only actions are on a draft.
    --}}
    <div class="page-enter space-y-6"
        x-data="{
            submitting: false,
            confirmOpen: false, confirmMessage: '', confirmFormId: null, confirmTitle: '', confirmLabel: '',
        }">

        <div class="flex flex-wrap items-center gap-2 font-body-sm text-body-sm">
            <a href="{{ route('admin.settlements.index') }}"
                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                title="{{ __('العودة إلى المقاصة') }}">
                <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>{{ __('المقاصة') }}
            </a>
        </div>

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="font-display-lg text-display-lg text-on-surface"><span dir="ltr">{{ $settlement->reference }}</span></h1>
                    <x-settlement-status :status="$settlement->status" />
                </div>
                <p class="mt-1 font-body-lg text-on-surface-variant">
                    <span dir="ltr">{{ $settlement->period_from->translatedFormat('d M Y') }} - {{ $settlement->period_to->translatedFormat('d M Y') }}</span>
                </p>
            </div>

            @unless ($settlement->isConfirmed())
                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('admin.settlements.destroy', $settlement) }}" id="delete-settlement" class="hidden">
                        @csrf @method('DELETE')
                    </form>
                    <button type="button"
                        @click="confirmOpen = true;
                                confirmTitle = @js(__('حذف المسودّة'));
                                confirmMessage = @js(__('سيتم حذف هذه المسودّة نهائياً. لن تتأثر أي حركة مالية.'));
                                confirmLabel = @js(__('حذف'));
                                confirmFormId = 'delete-settlement'"
                        class="flex items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2.5 font-body-sm font-bold text-error transition-colors hover:bg-error/10"
                        title="{{ __('حذف المسودّة') }}">
                        <span class="material-symbols-outlined !text-xl">delete</span>{{ __('حذف المسودّة') }}
                    </button>

                    <form method="POST" action="{{ route('admin.settlements.confirm', $settlement) }}" id="confirm-settlement" class="hidden">
                        @csrf
                    </form>
                    <button type="button"
                        @click="confirmOpen = true;
                                confirmTitle = @js(__('تأكيد المقاصة'));
                                confirmMessage = @js(__('سيتم إقفال الفترة نهائياً ولا يمكن التراجع. تبدأ الفترة التالية من اليوم التالي.'));
                                confirmLabel = @js(__('تأكيد وإقفال'));
                                confirmFormId = 'confirm-settlement'"
                        class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95"
                        title="{{ __('تأكيد وإقفال الفترة') }}">
                        <span class="material-symbols-outlined !text-xl">lock</span>{{ __('تأكيد وإقفال') }}
                    </button>
                </div>
            @endunless
        </header>

        {{-- Document meta --}}
        <section class="reveal-up grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-card>
                <p class="font-body-sm text-on-surface-variant">{{ __('المدفوعات') }}</p>
                <p class="mt-2 font-numeral-lg text-numeral-lg font-bold text-primary"><span dir="ltr">{{ number_format($settlement->total_payments, 2) }} ₪</span></p>
            </x-card>
            <x-card>
                <p class="font-body-sm text-on-surface-variant">{{ __('المصروفات') }}</p>
                <p class="mt-2 font-numeral-lg text-numeral-lg font-bold text-error"><span dir="ltr">{{ number_format($settlement->total_expenses, 2) }} ₪</span></p>
            </x-card>
            <x-card>
                <p class="font-body-sm text-on-surface-variant">{{ __('الربح القابل للتوزيع') }}</p>
                <p class="mt-2 font-numeral-lg text-numeral-lg font-bold {{ $settlement->distributable_profit >= 0 ? 'text-success' : 'text-error' }}">
                    <span dir="ltr">{{ number_format($settlement->distributable_profit, 2) }} ₪</span>
                </p>
            </x-card>
            <x-card>
                <p class="font-body-sm text-on-surface-variant">{{ __('صافي المستحقات') }}</p>
                <p class="mt-2 font-numeral-lg text-numeral-lg font-bold {{ $settlement->total_net >= 0 ? 'text-success' : 'text-error' }}">
                    <span dir="ltr">{{ number_format($settlement->total_net, 2) }} ₪</span>
                </p>
            </x-card>
        </section>

        {{-- Lines --}}
        <x-card :title="__('تفاصيل الشركاء')" :subtitle="__('النسب والمبالغ كما كانت وقت تنفيذ المقاصة')">
            @if ($settlement->lines->isEmpty())
                <div class="flex flex-col items-center gap-2 py-12 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/60">handshake</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد بنود في هذه المقاصة') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[__('الشريك'), __('النسبة'), __('الاستحقاق'), __('المسحوب'), __('الصافي')]">
                    <x-slot:table>
                        @foreach ($settlement->lines as $line)
                            <tr class="hover:bg-surface-container-low">
                                <td class="px-4 py-3 font-body-sm font-bold text-on-surface">{{ $line->partner_name }}</td>
                                <td class="px-4 py-3 font-body-sm text-on-surface-variant"><span dir="ltr">{{ number_format($line->percentage, 2) }}%</span></td>
                                <td class="px-4 py-3 font-body-sm text-on-surface"><span dir="ltr">{{ number_format($line->entitlement, 2) }} ₪</span></td>
                                <td class="px-4 py-3 font-body-sm text-on-surface-variant"><span dir="ltr">{{ number_format($line->drawn, 2) }} ₪</span></td>
                                <td class="px-4 py-3"><x-settlement-net :net="$line->net" /></td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($settlement->lines as $line)
                            <div class="tonal-elevation rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-body-lg font-bold text-on-surface">{{ $line->partner_name }}</p>
                                    <span class="font-body-sm text-body-sm text-on-surface-variant"><span dir="ltr">{{ number_format($line->percentage, 2) }}%</span></span>
                                </div>
                                <dl class="mt-3 grid grid-cols-2 gap-3 font-body-sm text-body-sm">
                                    <div>
                                        <dt class="text-outline">{{ __('الاستحقاق') }}</dt>
                                        <dd class="mt-0.5 font-bold text-on-surface"><span dir="ltr">{{ number_format($line->entitlement, 2) }} ₪</span></dd>
                                    </div>
                                    <div>
                                        <dt class="text-outline">{{ __('المسحوب') }}</dt>
                                        <dd class="mt-0.5 font-bold text-on-surface-variant"><span dir="ltr">{{ number_format($line->drawn, 2) }} ₪</span></dd>
                                    </div>
                                </dl>
                                <div class="mt-3 border-t border-outline-variant/60 pt-3">
                                    <x-settlement-net :net="$line->net" />
                                </div>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <div class="mt-6 grid grid-cols-1 gap-3 rounded-2xl border border-outline-variant/60 bg-surface-container-low p-4 font-body-sm text-body-sm sm:grid-cols-3">
                    <div class="flex items-center justify-between gap-2 sm:block">
                        <span class="text-on-surface-variant">{{ __('إجمالي الاستحقاق') }}</span>
                        <span class="font-bold text-on-surface sm:mt-1 sm:block"><span dir="ltr">{{ number_format($settlement->total_entitlement, 2) }} ₪</span></span>
                    </div>
                    <div class="flex items-center justify-between gap-2 sm:block">
                        <span class="text-on-surface-variant">{{ __('إجمالي المسحوب') }}</span>
                        <span class="font-bold text-on-surface sm:mt-1 sm:block"><span dir="ltr">{{ number_format($settlement->total_drawn, 2) }} ₪</span></span>
                    </div>
                    <div class="flex items-center justify-between gap-2 sm:block">
                        <span class="text-on-surface-variant">{{ __('المحتجز للمنصّة') }}</span>
                        <span class="font-bold text-on-surface sm:mt-1 sm:block"><span dir="ltr">{{ number_format($settlement->retained, 2) }} ₪</span></span>
                    </div>
                </div>
            @endif
        </x-card>

        {{-- Audit trail --}}
        <x-card :title="__('بيانات التنفيذ')">
            <dl class="grid grid-cols-1 gap-4 font-body-sm text-body-sm sm:grid-cols-2">
                <div>
                    <dt class="text-on-surface-variant">{{ __('نُفّذت بواسطة') }}</dt>
                    <dd class="mt-1 font-bold text-on-surface">{{ $settlement->admin?->name ?? __('غير معروف') }}</dd>
                </div>
                <div>
                    <dt class="text-on-surface-variant">{{ __('تاريخ الإنشاء') }}</dt>
                    <dd class="mt-1 font-bold text-on-surface"><span dir="ltr">{{ $settlement->created_at->translatedFormat('d M Y H:i') }}</span></dd>
                </div>
                <div>
                    <dt class="text-on-surface-variant">{{ __('تاريخ الإقفال') }}</dt>
                    <dd class="mt-1 font-bold text-on-surface">
                        @if ($settlement->confirmed_at)
                            <span dir="ltr">{{ $settlement->confirmed_at->translatedFormat('d M Y H:i') }}</span>
                        @else
                            <span class="text-on-surface-variant">{{ __('لم تُقفل بعد') }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-on-surface-variant">{{ __('ملاحظات') }}</dt>
                    <dd class="mt-1 whitespace-pre-line text-on-surface">{{ $settlement->notes ?: __('لا توجد ملاحظات') }}</dd>
                </div>
            </dl>
        </x-card>

        <x-confirm-modal />
    </div>
@endsection
