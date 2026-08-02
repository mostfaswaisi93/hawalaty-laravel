@extends('layouts.app')

@section('title', __('سجل الصرف - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('سجل الصرف') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('يُكتب مرة ولا يُعدَّل — هذا هو الدفتر الذي يقرؤه المفتّش.') }}</p>
            </div>
            <a href="{{ route('pharmacy.search') }}"
                class="flex items-center gap-2 rounded-xl bg-surface-container px-5 py-2.5 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                <span class="material-symbols-outlined !text-xl">search</span>
                {{ __('بحث بالمادة الفعّالة') }}
            </a>
        </header>

        <div class="reveal-up grid grid-cols-1 gap-4 sm:grid-cols-4" style="--d:60ms">
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('مواد خاضعة للرقابة') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold">{{ $summary['controlled'] }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('أدوية موصوفة') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold">{{ $summary['prescription'] }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('سلسلة تبريد') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold">{{ $summary['coldChain'] }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('بلا تصنيف ATC') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold {{ $summary['unclassified'] > 0 ? 'text-secondary' : '' }}">{{ $summary['unclassified'] }}</p>
                <p class="mt-1 font-body-sm text-outline">{{ __('البدائل لا تظهر بدونه') }}</p>
            </x-card>
        </div>

        <form method="GET" action="{{ route('pharmacy.index') }}" class="reveal-up grid grid-cols-1 gap-3 sm:grid-cols-4" style="--d:100ms">
            <x-keep-per-page />
            <div class="relative sm:col-span-2">
                <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">search</span>
                <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('المريض أو رقم الوصفة أو الطبيب...') }}" aria-label="{{ __('بحث') }}"
                    class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
            </div>
            <div>
                <label for="reg-from" class="sr-only">{{ __('من تاريخ') }}</label>
                <input id="reg-from" type="date" name="from" value="{{ $from }}" dir="ltr"
                    class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
            </div>
            <div class="flex gap-3">
                <label for="reg-to" class="sr-only">{{ __('إلى تاريخ') }}</label>
                <input id="reg-to" type="date" name="to" value="{{ $to }}" dir="ltr"
                    class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                <button type="submit" class="rounded-2xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container">{{ __('عرض') }}</button>
            </div>

            <div class="sm:col-span-4">
                <label for="reg-scope" class="sr-only">{{ __('النطاق') }}</label>
                <select id="reg-scope" name="scope" onchange="this.form.submit()"
                    class="rounded-2xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                    <option value="">{{ __('المواد الخاضعة للرقابة فقط') }}</option>
                    <option value="all" @selected(($scope ?? '') === 'all')>{{ __('كل عمليات الصرف') }}</option>
                </select>
            </div>
        </form>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($records->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">prescriptions</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد عمليات صرف مسجّلة') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[
                    __('التاريخ'),
                    __('الدواء'),
                    ['label' => __('الكمية'), 'align' => 'end'],
                    __('المريض'),
                    __('الطبيب والوصفة'),
                    __('الجدول'),
                    __('الصيدلي'),
                ]">
                    <x-slot:table>
                        @foreach ($records as $record)
                            <tr class="reveal-up transition-colors hover:bg-surface-container-low/50" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $record->dispensed_at?->translatedFormat('j M Y - H:i') }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-body-sm font-bold">{{ $record->item?->name ?? '—' }}</p>
                                    @if ($record->batch)
                                        <p class="font-body-sm text-outline" dir="ltr">{{ $record->batch->batch_number }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold" dir="ltr">{{ rtrim(rtrim(number_format((float) $record->quantity, 3), '0'), '.') }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-body-sm">{{ $record->patient_name }}</p>
                                    @if ($record->patient_id_number)
                                        <p class="font-body-sm text-outline" dir="ltr">{{ $record->patient_id_number }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-body-sm text-on-surface-variant">
                                    {{ $record->prescriber_name ?: '—' }}
                                    @if ($record->prescription_number)
                                        <span class="block text-outline" dir="ltr">{{ $record->prescription_number }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    @if ($record->isControlled())
                                        <span class="rounded-lg bg-error/10 px-2 py-1 font-body-sm font-bold text-error">{{ $record->control_schedule }}</span>
                                    @else
                                        <span class="font-body-sm text-outline">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $record->teamMember?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($records as $record)
                            <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-body-lg font-bold">{{ $record->item?->name }}</p>
                                        <p class="font-body-sm text-outline">{{ $record->patient_name }}</p>
                                    </div>
                                    @if ($record->isControlled())
                                        <span class="whitespace-nowrap rounded-lg bg-error/10 px-2 py-1 font-body-sm font-bold text-error">{{ $record->control_schedule }}</span>
                                    @endif
                                </div>
                                <p class="mt-2 font-body-sm text-on-surface-variant">{{ $record->dispensed_at?->translatedFormat('j M Y - H:i') }}</p>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$records" :perPage="$perPage" />
            @endif
        </section>
    </div>
@endsection
