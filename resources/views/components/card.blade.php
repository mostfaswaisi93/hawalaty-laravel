@props(['title' => null, 'subtitle' => null, 'padded' => true])

<div {{ $attributes->class(['tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest', 'p-6' => $padded]) }}>
    @if ($title || isset($actions))
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                @if ($title)
                    <h3 class="font-headline-md text-headline-md">{{ $title }}</h3>
                @endif
                @if ($subtitle)
                    <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</div>
