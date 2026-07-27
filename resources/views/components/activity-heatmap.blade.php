{{--
    A GitHub-contributions-style year grid: one cell per day, shaded by how many
    orders that day carried.

    LAYOUT / DIRECTION
    7 rows (Sun..Sat) x ~53 week columns using `grid-auto-flow: column`, so the
    DOM stays in chronological order and the browser lays the columns out. Under
    dir=rtl the grid flows right-to-left by itself, putting the oldest week on
    the right and the newest on the left - the direction an Arabic reader scans.
    No mirroring, and the labels stay aligned because they share the grid.

    The columns are `1fr`, not a fixed 11px, so the grid FILLS the card. Fixed
    cells left roughly half the width empty on a desktop, which is what made the
    block look stranded. `aspect-square` keeps the cells square as they grow, and
    a max-width stops them ballooning on very wide screens.

    Month labels span their own weeks (`grid-column: span N`) instead of sitting
    in one 11px column each - Arabic month names are far wider than that, so they
    collided into "يوليوأغسطس" at every boundary.

    INTENSITY uses QUARTILES of the non-zero days, not a fraction of the max: one
    freak 60-order day would otherwise flatten every ordinary day to the lightest
    shade and make the whole year look dead.
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

    // Pad back to the Sunday on/before the start so every column is a whole week
    // and the weekday rows line up. dayOfWeek is 0=Sunday, matching the label
    // array the reports page already uses.
    $gridStart = $start->copy()->subDays($start->dayOfWeek);

    $total = (int) $counts->sum();

    $active = $counts->filter(fn ($n) => $n > 0)->values()->sort()->values();
    $percentile = function (float $p) use ($active) {
        if ($active->isEmpty()) {
            return 0;
        }

        return (int) $active[(int) floor(($active->count() - 1) * $p)];
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

    $weeks = [];
    $cursor = $gridStart->copy();
    while ($cursor->lte($end)) {
        $week = [];
        for ($d = 0; $d < 7; $d++) {
            $key = $cursor->toDateString();
            $inRange = $cursor->betweenIncluded($start, $end);
            $n = $inRange ? (int) ($counts[$key] ?? 0) : null;
            $week[] = ['date' => $cursor->copy(), 'count' => $n, 'level' => $n === null ? null : $level($n)];
            $cursor->addDay();
        }
        $weeks[] = $week;
    }

    $weekCount = count($weeks);

    // Group consecutive weeks into month runs so each label spans its month.
    $monthSpans = [];
    foreach ($weeks as $week) {
        $month = $week[0]['date']->format('Y-m');
        if ($monthSpans && end($monthSpans)['key'] === $month) {
            $monthSpans[count($monthSpans) - 1]['span']++;
        } else {
            $monthSpans[] = ['key' => $month, 'label' => $week[0]['date']->translatedFormat('M'), 'span' => 1];
        }
    }
    // A 1-week sliver at either edge has no room for a label and would collide
    // with its neighbour.
    foreach ($monthSpans as $i => $m) {
        if ($m['span'] < 2) {
            $monthSpans[$i]['label'] = '';
        }
    }

    $dayLabels = [__('أحد'), __('اثنين'), __('ثلاثاء'), __('أربعاء'), __('خميس'), __('جمعة'), __('سبت')];
@endphp

<div {{ $attributes->merge(['class' => 'tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6']) }}>
    <div class="mb-4 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <h2 class="font-headline-md text-headline-md text-on-surface">
            {{ $title ?? __(':count طلب خلال آخر سنة', ['count' => number_format($total)]) }}
        </h2>
        <p class="font-body-sm text-body-sm text-outline">
            <span dir="ltr">{{ $start->translatedFormat('M Y') }} - {{ $end->translatedFormat('M Y') }}</span>
        </p>
    </div>

    {{-- Below md the grid cannot shrink to a usable size, so IT scrolls rather
         than squashing - the page itself never scrolls sideways. --}}
    <div class="overflow-x-auto">
        <div class="flex min-w-[560px] gap-2">
            {{-- Weekday rail. Only alternate rows are labelled; seven labels do
                 not fit at this cell height. --}}
            <div class="grid shrink-0 gap-[3px] pt-[18px]" style="grid-template-rows: repeat(7, 1fr);">
                @foreach ($dayLabels as $i => $label)
                    <span class="flex items-center font-body-sm text-[10px] leading-none text-outline">{{ $i % 2 === 1 ? $label : '' }}</span>
                @endforeach
            </div>

            <div class="min-w-0 flex-1" style="max-width: {{ $weekCount * 22 + ($weekCount - 1) * 3 }}px;">
                <div class="mb-1.5 grid gap-[3px]" style="grid-template-columns: repeat({{ $weekCount }}, minmax(0, 1fr));">
                    @foreach ($monthSpans as $month)
                        <span class="truncate font-body-sm text-[10px] leading-none text-outline" style="grid-column: span {{ $month['span'] }};">{{ $month['label'] }}</span>
                    @endforeach
                </div>

                <div class="grid gap-[3px]"
                    style="grid-auto-flow: column; grid-template-rows: repeat(7, 1fr); grid-template-columns: repeat({{ $weekCount }}, minmax(0, 1fr));">
                    @foreach ($weeks as $week)
                        @foreach ($week as $cell)
                            @if ($cell['count'] === null)
                                {{-- Outside the window: keeps the grid rectangular. --}}
                                <span class="aspect-square rounded-[2px]" aria-hidden="true"></span>
                            @else
                                <span
                                    class="heat-{{ $cell['level'] }} aspect-square rounded-[2px] transition-transform duration-150 hover:scale-125"
                                    title="{{ $cell['count'] === 0
                                        ? __('لا طلبات').' - '.$cell['date']->translatedFormat('j F Y')
                                        : trans_choice('{1} طلب واحد|[2,*] :count طلبات', $cell['count'], ['count' => $cell['count']]).' - '.$cell['date']->translatedFormat('j F Y') }}"
                                ></span>
                            @endif
                        @endforeach
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3 flex items-center justify-end gap-1.5 font-body-sm text-[11px] text-outline">
        <span class="me-1">{{ __('أقل') }}</span>
        @for ($i = 0; $i <= 4; $i++)
            <span class="heat-{{ $i }} h-[11px] w-[11px] rounded-[2px]"></span>
        @endfor
        <span class="ms-1">{{ __('أكثر') }}</span>
    </div>
</div>
