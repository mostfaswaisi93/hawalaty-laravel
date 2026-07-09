@props(['variant' => 'primary', 'type' => 'button', 'href' => null, 'icon' => null])

@php
    $variants = [
        'primary' => 'bg-primary text-on-primary hover:bg-primary-container hover:text-on-primary-container',
        'secondary' => 'bg-surface-container text-on-surface hover:bg-surface-container-high border border-outline-variant',
        'danger' => 'bg-error text-on-error hover:opacity-90',
    ];

    $classes = 'inline-flex items-center justify-center gap-2 rounded-xl px-5 py-3 font-headline-md text-sm font-bold shadow-sm transition-all active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60 '
        . ($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<span class="material-symbols-outlined text-lg">{{ $icon }}</span>@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon)<span class="material-symbols-outlined text-lg">{{ $icon }}</span>@endif
        {{ $slot }}
    </button>
@endif
