@extends('layouts.app')

@section('title', __('الورديات والخزانات - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6" x-data="{ opening: false, submitting: false }">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الورديات والخزانات') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('العدّادات تقول كم انضخّ، والقياس يقول كم بقي — والفرق بينهما هو المطلوب إيجاده.') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('fuel.equipment') }}"
                    class="flex items-center gap-2 rounded-xl bg-surface-container px-5 py-2.5 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                    <span class="material-symbols-outlined !text-xl">oil_barrel</span>
                    {{ __('الخزانات والأسعار') }}
                </a>
                @unless ($openShift)
                    <button type="button" @click="opening = true" @disabled($tanks->isEmpty())
                        class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50">
                        <span class="material-symbols-outlined !text-xl">play_arrow</span>
                        {{ __('افتح وردية') }}
                    </button>
                @endunless
            </div>
        </header>

        @if ($tanks->isEmpty())
            <div class="flex items-start gap-3 rounded-2xl border border-secondary-container/40 bg-secondary-container/10 p-4 text-secondary">
                <span class="material-symbols-outlined flex-shrink-0">info</span>
                <p class="font-body-sm">
                    {{ __('أضف خزانًا ومضخة أولًا.') }}
                    <a href="{{ route('fuel.equipment') }}" class="font-bold underline">{{ __('الخزانات والأسعار') }}</a>
                </p>
            </div>
        @endif

        @if ($openShift)
            <form method="POST" action="{{ route('fuel.shifts.record', $openShift->id) }}" class="space-y-6" x-data="{ saving: false }" @submit="saving = true">
                @csrf
                @method('PUT')

                <x-card title="{{ __('قراءات المضخات') }}" subtitle="{{ __('لترات الاختبار تُضخّ وتعود للخزان — تُطرح من المبيعات وإلا ظهر فرق من العدم.') }}">
                    <x-table-responsive :headers="[
                        __('المضخة'),
                        ['label' => __('قراءة الفتح'), 'align' => 'end'],
                        ['label' => __('قراءة الإقفال'), 'align' => 'end'],
                        ['label' => __('لترات اختبار'), 'align' => 'end'],
                        ['label' => __('السعر'), 'align' => 'end'],
                        ['label' => __('المُباع'), 'align' => 'end'],
                    ]">
                        <x-slot:table>
                            @foreach ($openShift->readings as $reading)
                                <tr class="transition-colors hover:bg-surface-container-low/50">
                                    <td class="px-4 py-3">
                                        <p class="font-body-sm font-bold">{{ $reading->pump?->name }}</p>
                                        <p class="font-body-sm text-outline">{{ $reading->pump?->tank?->name }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg text-on-surface-variant" dir="ltr">{{ number_format((float) $reading->opening_reading, 3) }}</td>
                                    <td class="px-4 py-3 text-end">
                                        <input type="number" step="0.001" min="0" name="readings[{{ $reading->id }}][closing]"
                                            value="{{ $reading->closing_reading !== null ? (float) $reading->closing_reading : '' }}" dir="ltr"
                                            aria-label="{{ __('قراءة إقفال :pump', ['pump' => $reading->pump?->name]) }}"
                                            class="w-32 rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-end font-numeral-lg outline-none focus:ring-2 focus:ring-primary" />
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <input type="number" step="0.001" min="0" name="readings[{{ $reading->id }}][test]" value="{{ (float) $reading->test_litres }}" dir="ltr"
                                            aria-label="{{ __('لترات اختبار :pump', ['pump' => $reading->pump?->name]) }}"
                                            class="w-24 rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-end font-numeral-lg outline-none focus:ring-2 focus:ring-primary" />
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <input type="number" step="0.001" min="0" name="readings[{{ $reading->id }}][price]" value="{{ (float) $reading->unit_price }}" dir="ltr"
                                            aria-label="{{ __('سعر :pump', ['pump' => $reading->pump?->name]) }}"
                                            class="w-24 rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-end font-numeral-lg outline-none focus:ring-2 focus:ring-primary" />
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold" dir="ltr">{{ number_format($reading->litresSold(), 3) }}</td>
                                </tr>
                            @endforeach
                        </x-slot:table>

                        <x-slot:cards>
                            @foreach ($openShift->readings as $reading)
                                <div class="space-y-2 rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                    <p class="font-body-lg font-bold">{{ $reading->pump?->name }}</p>
                                    <p class="font-body-sm text-outline" dir="ltr">{{ __('الفتح') }}: {{ number_format((float) $reading->opening_reading, 3) }}</p>
                                    <input type="number" step="0.001" min="0" name="readings[{{ $reading->id }}][closing]"
                                        value="{{ $reading->closing_reading !== null ? (float) $reading->closing_reading : '' }}" dir="ltr"
                                        placeholder="{{ __('قراءة الإقفال') }}"
                                        aria-label="{{ __('قراءة إقفال :pump', ['pump' => $reading->pump?->name]) }}"
                                        class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 font-numeral-lg outline-none focus:ring-2 focus:ring-primary" />
                                </div>
                            @endforeach
                        </x-slot:cards>
                    </x-table-responsive>
                </x-card>

                <x-card title="{{ __('قياس الخزانات') }}" subtitle="{{ __('ما يقوله العصا أو الجهاز فعلًا — وأي توريد وصل أثناء الوردية.') }}">
                    <x-table-responsive :headers="[
                        __('الخزان'),
                        ['label' => __('قياس الفتح'), 'align' => 'end'],
                        ['label' => __('توريدات'), 'align' => 'end'],
                        ['label' => __('قياس الإقفال'), 'align' => 'end'],
                        ['label' => __('الخارج فعليًا'), 'align' => 'end'],
                    ]">
                        <x-slot:table>
                            @foreach ($openShift->dips as $dip)
                                <tr class="transition-colors hover:bg-surface-container-low/50">
                                    <td class="px-4 py-3 font-body-sm font-bold">{{ $dip->tank?->name }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg text-on-surface-variant" dir="ltr">{{ number_format((float) $dip->opening_dip, 3) }}</td>
                                    <td class="px-4 py-3 text-end">
                                        <input type="number" step="0.001" min="0" name="dips[{{ $dip->id }}][deliveries]" value="{{ (float) $dip->deliveries }}" dir="ltr"
                                            aria-label="{{ __('توريدات :tank', ['tank' => $dip->tank?->name]) }}"
                                            class="w-28 rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-end font-numeral-lg outline-none focus:ring-2 focus:ring-primary" />
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <input type="number" step="0.001" min="0" name="dips[{{ $dip->id }}][closing]"
                                            value="{{ $dip->closing_dip !== null ? (float) $dip->closing_dip : '' }}" dir="ltr"
                                            aria-label="{{ __('قياس إقفال :tank', ['tank' => $dip->tank?->name]) }}"
                                            class="w-32 rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-end font-numeral-lg outline-none focus:ring-2 focus:ring-primary" />
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold" dir="ltr">
                                        {{ $dip->litresGone() === null ? '—' : number_format($dip->litresGone(), 3) }}
                                    </td>
                                </tr>
                            @endforeach
                        </x-slot:table>

                        <x-slot:cards>
                            @foreach ($openShift->dips as $dip)
                                <div class="space-y-2 rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                    <p class="font-body-lg font-bold">{{ $dip->tank?->name }}</p>
                                    <input type="number" step="0.001" min="0" name="dips[{{ $dip->id }}][closing]"
                                        value="{{ $dip->closing_dip !== null ? (float) $dip->closing_dip : '' }}" dir="ltr"
                                        placeholder="{{ __('قياس الإقفال') }}"
                                        aria-label="{{ __('قياس إقفال :tank', ['tank' => $dip->tank?->name]) }}"
                                        class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 font-numeral-lg outline-none focus:ring-2 focus:ring-primary" />
                                </div>
                            @endforeach
                        </x-slot:cards>
                    </x-table-responsive>

                    <div class="mt-6 flex flex-wrap justify-end gap-3 border-t border-outline-variant/50 pt-5">
                        <button type="submit" :disabled="saving"
                            class="rounded-xl bg-surface-container px-5 py-3 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high disabled:cursor-not-allowed disabled:opacity-60"
                            x-text="saving ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ القراءات'))">{{ __('حفظ القراءات') }}</button>
                    </div>
                </x-card>
            </form>

            @include('fuel._reconciliation', ['rows' => $reconciliation, 'service' => $service])

            <form method="POST" action="{{ route('fuel.shifts.close', $openShift->id) }}"
                @submit.prevent="if (confirm('{{ __('إقفال الوردية؟ ستُرحَّل المبيعات حسب العدّادات ولا يمكن التراجع.') }}')) $el.submit()">
                @csrf
                <button type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 sm:w-auto">
                    <span class="material-symbols-outlined !text-xl">stop_circle</span>
                    {{ __('أقفل الوردية ورحّل المبيعات') }}
                </button>
            </form>
        @endif

        <x-card title="{{ __('الورديات السابقة') }}">
            @if ($shifts->isEmpty())
                <p class="py-8 text-center font-body-sm text-on-surface-variant">{{ __('لا توجد ورديات') }}</p>
            @else
                <ul class="divide-y divide-outline-variant/60 rounded-2xl border border-outline-variant">
                    @foreach ($shifts as $entry)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <a href="{{ route('fuel.shift', $entry->id) }}" class="font-body-lg font-bold transition-colors hover:text-primary">
                                    {{ $entry->name ?: $entry->shift_date?->translatedFormat('j M Y') }}
                                </a>
                                <p class="font-body-sm text-outline">{{ $entry->teamMember?->name }}</p>
                            </div>
                            <span class="whitespace-nowrap rounded-lg px-2 py-1 font-body-sm font-bold {{ $entry->isOpen() ? 'bg-secondary-container/40 text-secondary' : 'bg-success/10 text-success' }}">
                                {{ $entry->statusLabel() }}
                            </span>
                        </li>
                    @endforeach
                </ul>

                <x-table-footer :paginator="$shifts" :perPage="$perPage" />
            @endif
        </x-card>

        <div x-show="opening" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="shift-title">
            <div class="fixed inset-0 bg-black/40" x-show="opening" x-transition.opacity @click="opening = false"></div>
            <div class="relative z-10 w-full max-w-md rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                x-show="opening" @keydown.escape.window="opening = false"
                x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

                <x-modal-close on-close="opening = false" />

                <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
                    <h3 id="shift-title" class="font-headline-md text-headline-md">{{ __('افتح وردية') }}</h3>
                    <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">{{ __('تُنقل قراءات الإقفال السابقة تلقائيًا — إعادة كتابتها يدويًا هي كيف تضيع مبيعات ليلة.') }}</p>
                </div>

                <form method="POST" action="{{ route('fuel.shifts.open') }}" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf

                    <div class="space-y-1.5">
                        <label for="shift-date" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('التاريخ') }}</label>
                        <input id="shift-date" type="date" name="shiftDate" value="{{ now()->toDateString() }}" dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="shift-name" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اسم الوردية') }}</label>
                        <input id="shift-name" name="name" maxlength="255" placeholder="{{ __('مثال: صباحية') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="opening = false" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                            x-text="submitting ? @js(__('جارٍ الفتح...')) : @js(__('افتح'))">{{ __('افتح') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
