@extends('layouts.app')

@section('title', __('أمر إنتاج - حوالاتي'))

@php $componentValue = $components->sum(fn ($row) => $row['quantity'] * (float) $row['item']->cost); @endphp

@section('content')
    <div class="page-enter space-y-6">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div class="min-w-0">
                <a href="{{ route('production.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                    <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                    {{ __('الإنتاج') }}
                </a>
                <h1 class="mt-1 font-display-lg text-display-lg text-on-surface">{{ $run->item?->name }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant" dir="ltr">
                    {{ number_format((float) $run->quantity, 3) }} · {{ $run->statusLabel() }}
                </p>
            </div>

            @if ($run->isDraft())
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('production.cancel', $run->id) }}"
                        @submit.prevent="if (confirm('{{ __('إلغاء أمر الإنتاج؟') }}')) $el.submit()">
                        @csrf
                        <button type="submit" class="rounded-xl bg-surface-container px-5 py-2.5 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">{{ __('إلغاء الأمر') }}</button>
                    </form>
                    <form method="POST" action="{{ route('production.post', $run->id) }}"
                        @submit.prevent="if (confirm('{{ __('ترحيل الإنتاج؟ ستُخصم المكوّنات وتُضاف الكمية المنتَجة، ولا يمكن التراجع.') }}')) $el.submit()">
                        @csrf
                        <button type="submit" class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                            <span class="material-symbols-outlined !text-xl">factory</span>
                            {{ __('ترحيل الإنتاج') }}
                        </button>
                    </form>
                </div>
            @endif
        </header>

        @if ($run->isDraft())
            <div class="reveal-up flex items-start gap-3 rounded-2xl border border-secondary-container/40 bg-secondary-container/10 p-4 text-secondary" style="--d:60ms">
                <span class="material-symbols-outlined flex-shrink-0">info</span>
                <p class="font-body-sm">{{ __('مسودة. راجع المكوّنات المطلوبة أدناه — عند الترحيل تُخصم من المخزون فورًا.') }}</p>
            </div>
        @endif

        <div class="reveal-up grid grid-cols-1 gap-4 sm:grid-cols-3" style="--d:100ms">
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('قيمة المكوّنات') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold" dir="ltr">{{ number_format($componentValue, 2) }} ₪</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('تكلفة الوحدة المنتَجة') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold" dir="ltr">
                    {{ $run->isDraft()
                        ? number_format((float) $run->quantity > 0 ? $componentValue / (float) $run->quantity : 0, 3)
                        : number_format((float) $run->unit_cost, 3) }}
                </p>
                @if ($run->isDraft())
                    <p class="mt-1 font-body-sm text-outline">{{ __('تقديري حتى الترحيل') }}</p>
                @endif
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الدفعة') }}</p>
                <p class="mt-1 font-body-lg font-bold" dir="ltr">{{ $run->batch_number ?: '—' }}</p>
                @if ($run->expires_at)
                    <p class="mt-1 font-body-sm text-outline" dir="ltr">{{ __('تنتهي في') }} {{ $run->expires_at->format('Y-m-d') }}</p>
                @endif
            </x-card>
        </div>

        <x-card title="{{ __('المكوّنات المطلوبة') }}" subtitle="{{ __('بعد تفكيك الوصفة حتى الأصناف التي يُمسك لها مخزون.') }}">
            @if ($components->isEmpty())
                <p class="py-8 text-center font-body-sm text-on-surface-variant">{{ __('وصفة هذا الصنف فارغة') }}</p>
            @else
                <x-table-responsive :headers="[__('الصنف'), ['label' => __('المطلوب'), 'align' => 'end'], ['label' => __('التكلفة'), 'align' => 'end']]">
                    <x-slot:table>
                        @foreach ($components as $row)
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="px-4 py-3 font-body-sm font-bold">{{ $row['item']->name }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">
                                    {{ rtrim(rtrim(number_format($row['quantity'], 4), '0'), '.') }} {{ $row['item']->unit()->shortLabel() }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format($row['quantity'] * (float) $row['item']->cost, 3) }}</td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($components as $row)
                            <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <p class="font-body-lg font-bold">{{ $row['item']->name }}</p>
                                <p class="mt-1 font-numeral-lg" dir="ltr">{{ rtrim(rtrim(number_format($row['quantity'], 4), '0'), '.') }} {{ $row['item']->unit()->shortLabel() }}</p>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>
            @endif

            @if ($run->produced_at)
                <p class="mt-4 font-body-sm text-outline">
                    {{ __('رُحِّل في :date', ['date' => $run->produced_at->translatedFormat('j F Y - H:i')]) }}
                    @if ($run->teamMember)
                        — {{ $run->teamMember->name }}
                    @endif
                </p>
            @endif
        </x-card>
    </div>
@endsection
