@props([
    'label' => null,
    'name',
    'type' => 'text',
    'icon' => null,
    'placeholder' => null,
    'value' => null,
    'dir' => null,
])

@php
    $hasError = $errors->has($name);

    $effectiveLtr = $dir ? ($dir === 'ltr') : (app()->getLocale() !== 'ar');
    $leadingIconPos = $effectiveLtr ? 'left-0 pl-3' : 'right-0 pr-3';
@endphp

<div class="space-y-2">
    @if ($label)
        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" for="{{ $name }}">
            {{ $label }}
        </label>
    @endif

    <div class="relative">
        @if ($icon)
            <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 {{ $leadingIconPos }} text-outline">{{ $icon }}</span>
        @endif

        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @if ($dir) dir="{{ $dir }}" @endif
            value="{{ old($name, $value) }}"
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            {{ $attributes->class([
                'w-full rounded-xl border bg-surface-container-lowest py-3 pe-4 text-body-lg outline-none transition-all focus:border-transparent focus:ring-2 focus:ring-primary',
                'ps-11' => $icon,
                'ps-4' => ! $icon,
                'border-error focus:ring-error' => $hasError,
                'border-outline-variant' => ! $hasError,
            ]) }} />
    </div>

    @error($name)
        <p class="text-body-sm text-error">{{ $message }}</p>
    @enderror
</div>
