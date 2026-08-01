@props(['name', 'title' => null])

<div
    x-data="{ open: false, close() { this.open = false } }"
    x-on:open-modal-{{ $name }}.window="open = true"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-[100] flex items-center justify-center p-4"
    style="display: none;">
    <div class="fixed inset-0 bg-black/40" x-show="open" x-transition.opacity></div>

    <div
        x-show="open"
        x-init="$watch('open', v => v && $nextTick(() => $el.querySelector('input:not([type=hidden]), select, textarea')?.focus()))"
        role="dialog" aria-modal="true" @if ($title) aria-labelledby="modal-{{ $name }}-title" @endif
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto overflow-x-hidden rounded-3xl bg-surface-container-lowest shadow-2xl">
        <x-modal-close on-close="close()" />

        @if ($title)
            <h3 id="modal-{{ $name }}-title" class="border-b border-outline-variant/60 pe-14 ps-6 py-4 font-headline-md text-headline-md">{{ $title }}</h3>
        @endif

        <div class="p-6">
            {{ $slot }}
        </div>
    </div>
</div>
