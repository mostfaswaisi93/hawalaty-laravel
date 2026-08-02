{{--
    Meters against dips.

    The sign matters: a negative variance means more left the tank than the pumps
    accounted for, which is the direction that indicates a leak or a theft rather
    than a rounding error.
--}}
<x-card title="{{ __('التسوية الرطبة') }}" subtitle="{{ __('فرق يتجاوز :percent% من الكمية المنضوخة يستحق التحقيق لا التجاهل.', ['percent' => \App\Services\Tenant\FuelService::VARIANCE_THRESHOLD]) }}">
    @if ($rows->isEmpty())
        <p class="py-8 text-center font-body-sm text-on-surface-variant">{{ __('لا توجد خزانات في هذه الوردية') }}</p>
    @else
        <x-table-responsive :headers="[
            __('الخزان'),
            ['label' => __('حسب العدّادات'), 'align' => 'end'],
            ['label' => __('حسب القياس'), 'align' => 'end'],
            ['label' => __('الفرق'), 'align' => 'end'],
            ['label' => __('النسبة'), 'align' => 'end'],
            ['label' => __('قيمة المبيعات'), 'align' => 'end'],
        ]">
            <x-slot:table>
                @foreach ($rows as $row)
                    @php $flagged = $service->needsInvestigation($row['percent']); @endphp
                    <tr class="transition-colors hover:bg-surface-container-low/50">
                        <td class="px-4 py-3 font-body-sm font-bold">{{ $row['tank']?->name ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format($row['metered'], 3) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ $row['dipped'] === null ? '—' : number_format($row['dipped'], 3) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold {{ $flagged ? 'text-error' : '' }}" dir="ltr">
                            {{ $row['variance'] === null ? '—' : ($row['variance'] > 0 ? '+' : '').number_format($row['variance'], 3) }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg {{ $flagged ? 'font-bold text-error' : '' }}" dir="ltr">
                            {{ $row['percent'] === null ? '—' : number_format($row['percent'], 3).'%' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format($row['value'], 2) }}</td>
                    </tr>
                @endforeach
            </x-slot:table>

            <x-slot:cards>
                @foreach ($rows as $row)
                    @php $flagged = $service->needsInvestigation($row['percent']); @endphp
                    <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-body-lg font-bold">{{ $row['tank']?->name }}</p>
                            <span class="whitespace-nowrap font-numeral-lg font-bold {{ $flagged ? 'text-error' : '' }}" dir="ltr">
                                {{ $row['percent'] === null ? '—' : number_format($row['percent'], 3).'%' }}
                            </span>
                        </div>
                        <p class="mt-1 font-body-sm text-on-surface-variant" dir="ltr">
                            {{ number_format($row['metered'], 3) }} ↔ {{ $row['dipped'] === null ? '—' : number_format($row['dipped'], 3) }}
                        </p>
                    </div>
                @endforeach
            </x-slot:cards>
        </x-table-responsive>

        @if ($rows->contains(fn ($row) => $service->needsInvestigation($row['percent'])))
            <div class="mt-4 flex items-start gap-3 rounded-2xl border border-error/30 bg-error-container/10 p-4 text-error">
                <span class="material-symbols-outlined flex-shrink-0">warning</span>
                <div>
                    <p class="font-body-sm font-bold">{{ __('فرق يستدعي التحقيق') }}</p>
                    <p class="mt-1 font-body-sm">{{ __('فرق سالب متكرّر يعني تسريبًا أو سرقة — وكلاهما واجب الإبلاغ، لا مسألة إدارية.') }}</p>
                </div>
            </div>
        @endif
    @endif
</x-card>
