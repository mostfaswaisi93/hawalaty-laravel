@extends('layouts.app')

@section('title', __('تحقّق من السلسلة - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6">
        <header>
            <a href="{{ route('invoices.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                {{ __('الفوترة الإلكترونية') }}
            </a>
            <h1 class="mt-1 font-display-lg text-display-lg text-on-surface">{{ __('تحقّق من السلسلة') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('تُعاد بصمة كل مستند من محتواه المخزَّن وتُقارن بما هو مسجَّل — سلسلة لم يتحقّق منها أحد لا تثبت شيئًا.') }}</p>
        </header>

        @if ($results->isEmpty())
            <div class="reveal-scale flex flex-col items-center gap-3 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest py-16 text-center">
                <span class="material-symbols-outlined !text-5xl text-outline/50">link</span>
                <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد مستندات لفحصها') }}</p>
            </div>
        @elseif ($broken === 0)
            <div class="reveal-up flex items-start gap-3 rounded-2xl border border-success/30 bg-success/5 p-4" style="--d:60ms">
                <span class="material-symbols-outlined flex-shrink-0 text-success">verified</span>
                <div>
                    <p class="font-body-sm font-bold text-success">{{ __('السلسلة سليمة') }}</p>
                    <p class="mt-1 font-body-sm text-success">{{ __(':count مستندًا، كل واحد يتبع ما قبله وبصمته تطابق محتواه.', ['count' => $results->count()]) }}</p>
                </div>
            </div>
        @else
            <div class="reveal-up flex items-start gap-3 rounded-2xl border border-error/30 bg-error-container/10 p-4" style="--d:60ms">
                <span class="material-symbols-outlined flex-shrink-0 text-error">gpp_bad</span>
                <div>
                    <p class="font-body-sm font-bold text-error">{{ __('السلسلة مكسورة عند :count مستند.', ['count' => $broken]) }}</p>
                    <p class="mt-1 font-body-sm text-error">{{ __('أول موضع مكسور هو موضع التعديل غالبًا، وما بعده مكسور بالتبعية. لا تُصلَح بإعادة الحساب — البصمة القديمة هي الدليل.') }}</p>
                </div>
            </div>
        @endif

        @unless ($results->isEmpty())
            <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                <x-table-responsive :headers="[
                    __('الرقم'),
                    __('النوع'),
                    __('التاريخ'),
                    ['label' => __('الإجمالي'), 'align' => 'end'],
                    __('الحالة'),
                ]">
                    <x-slot:table>
                        @foreach ($results as $row)
                            @php $entry = $row['invoice']; @endphp
                            <tr class="transition-colors hover:bg-surface-container-low/50 {{ $row['valid'] ? '' : 'bg-error-container/5' }}">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('invoices.show', $entry->id) }}" class="font-numeral-lg font-bold hover:text-primary" dir="ltr">{{ $entry->number }}</a>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $entry->type->label() }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $entry->issued_at?->translatedFormat('j M Y - H:i') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format((float) $entry->payable_amount, 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-lg px-2 py-1 font-body-sm font-bold {{ $row['valid'] ? 'bg-success/10 text-success' : 'bg-error/10 text-error' }}">
                                        <span class="material-symbols-outlined !text-base">{{ $row['valid'] ? 'check_circle' : 'error' }}</span>
                                        {{ $row['valid'] ? __('سليم') : __('مكسور') }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($results as $row)
                            @php $entry = $row['invoice']; @endphp
                            <a href="{{ route('invoices.show', $entry->id) }}" class="block rounded-2xl border p-4 {{ $row['valid'] ? 'border-outline-variant bg-surface-container-lowest' : 'border-error/30 bg-error-container/5' }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-numeral-lg font-bold" dir="ltr">{{ $entry->number }}</p>
                                        <p class="font-body-sm text-outline">{{ $entry->type->label() }}</p>
                                    </div>
                                    <span class="whitespace-nowrap rounded-lg px-2 py-1 font-body-sm font-bold {{ $row['valid'] ? 'bg-success/10 text-success' : 'bg-error/10 text-error' }}">
                                        {{ $row['valid'] ? __('سليم') : __('مكسور') }}
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>
            </section>
        @endunless
    </div>
@endsection
