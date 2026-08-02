@extends('layouts.app')

@section('title', __('أمر شراء - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div class="min-w-0">
                <a href="{{ route('purchases.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                    <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                    {{ __('المشتريات') }}
                </a>
                <h1 class="mt-1 font-display-lg text-display-lg text-on-surface" dir="ltr">{{ $purchase->reference ?: mb_substr($purchase->id, -8) }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">
                    {{ $purchase->supplier?->name ?? __('بدون مورد') }} — {{ $purchase->statusLabel() }}
                </p>
            </div>

            @if ($purchase->isDraft())
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('purchases.cancel', $purchase->id) }}"
                        @submit.prevent="if (confirm('{{ __('إلغاء أمر الشراء؟') }}')) $el.submit()">
                        @csrf
                        <button type="submit" class="rounded-xl bg-surface-container px-5 py-2.5 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">{{ __('إلغاء الأمر') }}</button>
                    </form>
                    <form method="POST" action="{{ route('purchases.receive', $purchase->id) }}"
                        @submit.prevent="if (confirm('{{ __('استلام البضاعة؟ ستدخل الكميات للمخزون وتتحدث التكلفة، ولا يمكن التراجع.') }}')) $el.submit()">
                        @csrf
                        <button type="submit" class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                            <span class="material-symbols-outlined !text-xl">inventory</span>
                            {{ __('استلام وترحيل للمخزون') }}
                        </button>
                    </form>
                </div>
            @endif
        </header>

        @if ($purchase->isDraft())
            <div class="reveal-up flex items-start gap-3 rounded-2xl border border-secondary-container/40 bg-secondary-container/10 p-4 text-secondary" style="--d:60ms">
                <span class="material-symbols-outlined flex-shrink-0">info</span>
                <p class="font-body-sm">{{ __('هذه مسودة. راجع الكميات والتكاليف ثم استلمها — بعد الاستلام لا تُعدَّل، ويُصحَّح الخطأ بمرتجع للمورد.') }}</p>
            </div>

            @include('purchases._form', [
                'action' => route('purchases.update', $purchase->id),
                'method' => 'PUT',
                'submitLabel' => __('حفظ التعديلات'),
            ])
        @else
            <x-card title="{{ __('الأصناف') }}">
                <x-table-responsive :headers="[
                    __('الصنف'),
                    ['label' => __('الكمية'), 'align' => 'end'],
                    ['label' => __('تكلفة الوحدة'), 'align' => 'end'],
                    ['label' => __('الضريبة'), 'align' => 'end'],
                    ['label' => __('الإجمالي'), 'align' => 'end'],
                    __('اللوط والصلاحية'),
                ]">
                    <x-slot:table>
                        @foreach ($purchase->lines as $line)
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="px-4 py-3 font-body-sm font-bold">{{ $line->name }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format((float) $line->quantity, 3) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format((float) $line->unit_cost, 3) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format((float) $line->tax_rate, 2) }}%</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold" dir="ltr">{{ number_format((float) $line->line_total, 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant" dir="ltr">
                                    {{ $line->batch_number ?: '—' }}
                                    @if ($line->expires_at)
                                        · {{ $line->expires_at->format('Y-m-d') }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($purchase->lines as $line)
                            <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <p class="font-body-lg font-bold">{{ $line->name }}</p>
                                <p class="mt-1 font-body-sm text-on-surface-variant" dir="ltr">
                                    {{ number_format((float) $line->quantity, 3) }} × {{ number_format((float) $line->unit_cost, 3) }}
                                </p>
                                <p class="mt-1 font-numeral-lg font-bold" dir="ltr">{{ number_format((float) $line->line_total, 2) }} ₪</p>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <dl class="mt-6 space-y-2 border-t border-outline-variant/50 pt-5">
                    <div class="flex justify-between font-body-lg">
                        <dt class="text-on-surface-variant">{{ __('المجموع قبل الضريبة') }}</dt>
                        <dd class="font-numeral-lg" dir="ltr">{{ number_format((float) $purchase->subtotal, 2) }} ₪</dd>
                    </div>
                    <div class="flex justify-between font-body-lg">
                        <dt class="text-on-surface-variant">{{ __('الضريبة') }}</dt>
                        <dd class="font-numeral-lg" dir="ltr">{{ number_format((float) $purchase->tax_total, 2) }} ₪</dd>
                    </div>
                    <div class="flex justify-between font-body-lg">
                        <dt class="text-on-surface-variant">{{ __('نقل ومصاريف') }}</dt>
                        <dd class="font-numeral-lg" dir="ltr">{{ number_format((float) $purchase->other_costs, 2) }} ₪</dd>
                    </div>
                    <div class="flex justify-between border-t border-outline-variant/50 pt-2 font-headline-md text-headline-md font-bold">
                        <dt>{{ __('الإجمالي') }}</dt>
                        <dd class="font-numeral-lg" dir="ltr">{{ number_format((float) $purchase->total, 2) }} ₪</dd>
                    </div>
                </dl>

                @if ($purchase->received_at)
                    <p class="mt-4 font-body-sm text-outline">
                        {{ __('استُلمت في :date', ['date' => $purchase->received_at->translatedFormat('j F Y - H:i')]) }}
                    </p>
                @endif
            </x-card>
        @endif
    </div>
@endsection
