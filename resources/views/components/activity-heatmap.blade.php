{{--
    A GitHub-contributions-style year grid: one cell per day, shaded by how many
    orders that day carried.

    Layout is 7 rows (Sun..Sat) x ~53 week columns, built with
    `grid-auto-flow: column` so the DOM stays in chronological order and the
    browser lays the columns out for us. That matters for RTL: under dir=rtl the
    grid flows right-to-left on its own, so the oldest week lands on the right
    and the newest on the left, which is the direction an Arabic reader scans.
    No mirroring hacks, and the month labels - being cells of the same grid -
    stay aligned automatically.

    Intensity uses QUARTILES of the non-zero days, not a fraction of the max.
    One freak 60-order day would otherwise flatten every ordinary day to the
    lightest shade and make the whole year look dead.
--}}
@props([
    'days' => [],          // ['Y-m-d' => int]
    'from' => null,        // Carbon|string - defaults to 12 months back
    'to' => null,          // Carbon|string - defaults to today
    'title' => null,
])

@php
    use Illuminate\Support\Carbon;

    $counts = collect($days);
    $end = $to ? Carbon::parse($to)->startOfDay() : Carbon::today();
    $start = $from ? Carbon::parse($from)->startOfDay() : $end->copy()->subYear()->addDay();

    // Pad back to the Sunday on/before the start so every column is a full week
    // and the weekday rows line up. dayOfWeek is 0=Sunday, matching the label
    // array the reports page already uses.
    $gridStart = $start->copy()->subDays($start->dayOfWeek);

    $total = (int) $counts->sum();

    // Quartile thresholds over days that actually had orders.
    $active = $counts->filter(fn ($n) => $n > 0)->values()->sort()->values();
    $percentile = function (float $p) use ($active) {
        if ($active->isEmpty()) {
            return 0;
        }
        $index = (int) floor(($active->count() - 1) * $p);

        return (int) $active[$index];
    };
    $q1 = $percentile(0.25);
    $q2 = $percentile(0.50);
    $q3 = $percentile(0.75);

    $level = function (int $n) use ($q1, $q2, $q3) {
        if ($n <= 0) return 0;
        if ($n <= $q1) return 1;
        if ($n <= $q2) return 2;
        if ($n <= $q3) return 3;
        return 4;
    };

    // Build week columns.
    $weeks = [];
    $cursor = $gridStart->copy();
    while ($cursor->lte($end)) {
        $week = [];
        for ($d = 0; $d < 7; $d++) {
            $key = $cursor->toDateString();
            $week[] = [
                'date' => $cursor->copy(),
                'key' => $key,
                'count' => $inRange = ($cursor->betweenIncluded($start, $end)) ? (int) ($counts[$key] ?? 0) : null,
                'level' => $inRange === null ? null : $level($inRange),
            ];
            $cursor->addDay();
        }
        $weeks[] = $week;
    }

    // A month label sits above the first column whose month differs from the
    // previous column's - same rule GitHub uses.
    $monthLabels = [];
    $previousMonth = null;
    foreach ($weeks as $i => $week) {
        $month = $week[0]['date']->month;
        $monthLabels[$i] = ($month !== $previousMonth) ? $week[0]['date']->translatedFormat('M') : '';
        $previousMonth = $month;
    }

    $dayLabels = [__('أحد'), __('اثنين'), __('ثلاثاء'), __('أربعاء'), __('خميس'), __('جمعة'), __('سبت')];
@endphp

<div {{ $attributes->merge(['class' => 'tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6']) }}>
    <div class="mb-5 flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="font-headline-md text-headline-md text-on-surface">
            {{ $title ?? __(':count طلب خلال آخر سنة', ['count' => number_format($total)]) }}
        </h2>
        <p class="font-body-sm text-body-sm text-outline">
            <span dir="ltr">{{ $start->translatedFormat('M Y') }} - {{ $end->translatedFormat('M Y') }}</span>
        </p>
    </div>

    {{-- The grid is wider than a phone, so IT scrolls rather than the page. --}}
    <div class="overflow-x-auto pb-1">
        <div class="inline-flex gap-2">
            {{-- Weekday rail. Only alternate rows are labelled, exactly like
                 GitHub - seven labels at this cell size would not fit. --}}
            <div class="grid shrink-0 gap-[3px] pt-[19px]" style="grid-template-rows: repeat(7, 11px);">
                @foreach ($dayLabels as $i => $label)
                    <span class="flex h-[11px] items-center font-body-sm text-[10px] leading-none text-outline">
                        {{ $i % 2 === 1 ? $label : '' }}
                    </span>
                @endforeach
            </div>

            <div>
                {{-- Month labels, one grid column per week so they stay aligned. --}}
                <div class="mb-1 grid gap-[3px]" style="grid-auto-flow: column; grid-auto-columns: 11px;">
                    @foreach ($weeks as $i => $week)
                        <span class="font-body-sm text-[10px] leading-none text-outline whitespace-nowrap">{{ $monthLabels[$i] }}</span>
                    @endforeach
                </div>

                <div class="grid gap-[3px]" style="grid-auto-flow: column; grid-template-rows: repeat(7, 11px); grid-auto-columns: 11px;">
                    @foreach ($weeks as $week)
                        @foreach ($week as $cell)
                            @if ($cell['count'] === null)
                                {{-- Outside the window: keeps the grid rectangular. --}}
                                <span class="h-[11px] w-[11px] rounded-[2px]" aria-hidden="true"></span>
                            @else
                                <span
                                    class="heat-{{ $cell['level'] }} h-[11px] w-[11px] rounded-[2px] transition-transform duration-150 hover:scale-125"
                                    title="{{ $cell['count'] === 0
                                        ? __('لا طلبات') . ' - ' . $cell['date']->translatedFormat('j F Y')
                                        : trans_choice('{1} طلب واحد|[2,*] :count طلبات', $cell['count'], ['count' => $cell['count']]) . ' - ' . $cell['date']->translatedFormat('j F Y') }}"
                                ></span>
                            @endif
                        @endforeach
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 flex items-center justify-end gap-2 font-body-sm text-[11px] text-outline">
        <span>{{ __('أقل') }}</span>
        @for ($i = 0; $i <= 4; $i++)
            <span class="heat-{{ $i }} h-[11px] w-[11px] rounded-[2px]"></span>
        @endfor
        <span>{{ __('أكثر') }}</span>
    </div>
</div>
