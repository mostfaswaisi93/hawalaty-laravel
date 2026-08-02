@extends('layouts.app')

@section('title', __('مطالبة ضمان - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6" x-data="{ submitting: false }">
        <header>
            <a href="{{ route('warranty.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                {{ __('الضمان والصيانة') }}
            </a>
            <h1 class="mt-1 font-display-lg text-display-lg text-on-surface">{{ $claim->customer_name }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">
                {{ $claim->item?->name }}
                @if ($claim->serialNumber)
                    — <span dir="ltr">{{ $claim->serialNumber->serial }}</span>
                @endif
            </p>
        </header>

        <div class="reveal-up grid grid-cols-1 gap-4 sm:grid-cols-4" style="--d:60ms">
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الحالة') }}</p>
                <p class="mt-1 inline-flex items-center gap-1.5 rounded-lg px-2 py-1 font-body-lg font-bold {{ $claim->status->tone() }}">
                    <span class="material-symbols-outlined !text-lg">{{ $claim->status->icon() }}</span>
                    {{ $claim->status->label() }}
                </p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الغطاء') }}</p>
                <p class="mt-1 font-body-lg font-bold {{ $claim->under_warranty ? 'text-success' : '' }}">
                    {{ $claim->under_warranty ? __('تحت الضمان') : __('خارج الضمان') }}
                </p>
                <p class="mt-1 font-body-sm text-outline">{{ __('حُسم وقت فتح المطالبة') }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('تكلفة الإصلاح') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold" dir="ltr">{{ number_format((float) $claim->repair_cost, 2) }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('تحمّله المحل') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold {{ $claim->absorbedCost() > 0 ? 'text-error' : '' }}" dir="ltr">
                    {{ number_format($claim->absorbedCost(), 2) }}
                </p>
            </x-card>
        </div>

        <x-card title="{{ __('العطل المُبلَّغ') }}">
            <p class="whitespace-pre-line font-body-lg text-on-surface">{{ $claim->issue }}</p>
            <p class="mt-3 font-body-sm text-outline">
                {{ $claim->reported_at?->translatedFormat('j F Y - H:i') }}
                @if ($claim->teamMember)
                    — {{ $claim->teamMember->name }}
                @endif
            </p>

            @if ($claim->serialNumber)
                <a href="{{ route('serials.show', $claim->serialNumber->id) }}"
                    class="mt-4 inline-flex items-center gap-1.5 font-body-sm font-bold text-primary transition-colors hover:underline">
                    <span class="material-symbols-outlined !text-lg">pin</span>
                    {{ __('تتبّع الوحدة') }}
                </a>
            @endif
        </x-card>

        <form method="POST" action="{{ route('warranty.update', $claim->id) }}" @submit="submitting = true">
            @csrf
            @method('PUT')

            <x-card title="{{ __('المعالجة') }}">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5 sm:col-span-2">
                        <label for="claim-status" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الحالة') }}</label>
                        <select id="claim-status" name="status" required
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            @foreach ($statuses as $case)
                                <option value="{{ $case->value }}" @selected($claim->status === $case)>{{ $case->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label for="claim-repair-cost" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('تكلفة الإصلاح') }}</label>
                        <input id="claim-repair-cost" type="number" step="0.01" min="0" name="repairCost" value="{{ (float) $claim->repair_cost }}" dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="claim-charged" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المبلغ المُحصَّل من العميل') }}</label>
                        <input id="claim-charged" type="number" step="0.01" min="0" name="chargedAmount" value="{{ (float) $claim->charged_amount }}" dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        <p class="font-body-sm text-outline">{{ __('الفرق هو ما يتحمّله المحل.') }}</p>
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <label for="claim-resolution" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('ما تم') }}</label>
                        <textarea id="claim-resolution" name="resolution" rows="3" maxlength="2000"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">{{ $claim->resolution }}</textarea>
                    </div>
                </div>

                @if ($claim->resolved_at)
                    <p class="mt-4 font-body-sm text-outline">{{ __('أُغلقت في :date', ['date' => $claim->resolved_at->translatedFormat('j F Y - H:i')]) }}</p>
                @endif

                <div class="mt-6 flex justify-end border-t border-outline-variant/50 pt-5">
                    <button type="submit" :disabled="submitting"
                        class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                        x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ المطالبة'))">{{ __('حفظ المطالبة') }}</button>
                </div>
            </x-card>
        </form>
    </div>
@endsection
