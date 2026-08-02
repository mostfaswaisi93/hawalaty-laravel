@props(['active'])

@php
    $tabs = [
        ['key' => 'margins', 'href' => route('margins.index'), 'icon' => 'trending_up', 'label' => __('الهامش')],
        ['key' => 'pricing', 'href' => route('margins.pricing'), 'icon' => 'sell', 'label' => __('التسعير')],
        ['key' => 'valuation', 'href' => route('margins.valuation'), 'icon' => 'inventory', 'label' => __('التقييم والراكد')],
    ];
@endphp

<nav class="reveal-up flex flex-wrap gap-2" style="--d:40ms" aria-label="{{ __('تقارير الهامش') }}">
    @foreach ($tabs as $tab)
        <a href="{{ $tab['href'] }}"
            @if ($active === $tab['key']) aria-current="page" @endif
            class="flex items-center gap-2 rounded-xl px-4 py-2.5 font-body-sm font-bold transition-colors {{ $active === $tab['key'] ? 'bg-primary text-on-primary shadow-lg' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high' }}">
            <span class="material-symbols-outlined !text-xl">{{ $tab['icon'] }}</span>
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
