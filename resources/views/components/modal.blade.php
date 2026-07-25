{{--
    Simple Alpine-based modal, used for confirm dialogs and small forms.
    Toggle it from anywhere on the page with `$dispatch('open-modal-<name>')`
    (e.g. a button: `@click="$dispatch('open-modal-confirm-delete')"`), and
    close it from inside the slot with `close()`.

    Usage:
        <x-modal name="confirm-delete" title="حذف العنصر">
            <p class="text-body-sm text-on-surface-variant">هل أنت متأكد؟ لا يمكن التراجع عن هذا الإجراء.</p>
            <div class="mt-6 flex justify-end gap-3">
                <x-button variant="secondary" @click="close()">إلغاء</x-button>
                <form method="POST" action="...">
                    @csrf @method('DELETE')
                    <x-button type="submit" variant="danger">حذف</x-button>
                </form>
            </div>
        </x-modal>
--}}
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
        class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto rounded-3xl bg-surface-container-lowest p-6 shadow-2xl">
        @if ($title)
            <h3 id="modal-{{ $name }}-title" class="mb-4 font-headline-md text-headline-md">{{ $title }}</h3>
        @endif

        {{ $slot }}
    </div>
</div>
