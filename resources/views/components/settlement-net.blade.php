{{--
    A partner's net clearing position, rendered as a signed chip.

    The sign carries the whole meaning here, so it is never left to the reader
    to work out from a minus glyph alone: positive is labelled "له" (the
    platform owes them), negative "عليه" (they over-drew). The amount is shown
    unsigned next to that label, because "-250 عليه" reads as a double
    negative in Arabic.
--}}
@props(['net'])

@php
    $net = round((float) $net, 2);
    $state = $net > 0 ? 'due' : ($net < 0 ? 'owed' : 'settled');

    $styles = [
        'due' => 'bg-success-container/20 text-success',
        'owed' => 'bg-error-container/40 text-error',
        'settled' => 'bg-surface-container-high text-on-surface-variant',
    ];

    $labels = [
        'due' => __('له'),
        'owed' => __('عليه'),
        'settled' => __('مسوّاة'),
    ];

    $icons = [
        'due' => 'trending_up',
        'owed' => 'trending_down',
        'settled' => 'check',
    ];
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-3 py-1 font-body-sm text-body-sm font-bold', $styles[$state]]) }}>
    <span class="material-symbols-outlined !text-base">{{ $icons[$state] }}</span>
    <span dir="ltr">{{ number_format(abs($net), 2) }} ₪</span>
    <span>{{ $labels[$state] }}</span>
</span>
