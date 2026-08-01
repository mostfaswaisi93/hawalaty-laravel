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
