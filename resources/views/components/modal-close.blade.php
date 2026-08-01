{{--
    The X in a modal's header corner.

    Positioned absolutely against the dialog (every dialog in this app is
    `relative`) rather than being placed inside the header markup, because those
    headers are not uniform - some are a plain <h3>, some drive their text with
    `x-text`, and a couple are <div> wrappers holding a title and a subtitle. One
    absolute button works for all of them and keeps the close affordance in the
    same spot on every modal.

    `end-4`, not `right-4`: in the Arabic RTL UI the end side is the LEFT, which
    is where the X belongs.

    `onClose` is the Alpine expression that closes THIS modal (e.g.
    `payOpen = false`, or `close()` for the shared x-modal component) - taken
    from the dialog's own Escape handler so the two can never disagree.
--}}
@props(['onClose'])

{{-- `showTipBelow` comes from the root x-data of whichever shell rendered the
     modal - app, admin or guest all define it. Safe because every dialog in this
     app lives inside one of those three; the standalone pages (welcome, the
     printable receipts, the PDF views) render no modals. --}}
{{-- `hideTip()` is folded into the click, not left to `@mouseleave`: closing the
     dialog removes this button from under the cursor, and a pointer that never
     "leaves" fires no mouseleave - the bubble would sit there after the modal
     had gone. --}}
<button type="button" @click="hideTip(); {{ $onClose }}"
    @mouseenter="showTipBelow($el, @js(__('إغلاق')))" @mouseleave="hideTip()"
    @focus="showTipBelow($el, @js(__('إغلاق')))" @blur="hideTip()"
    class="absolute top-3 z-10 flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-error focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary end-4"
    aria-label="{{ __('إغلاق') }}" title="{{ __('إغلاق') }}">
    <span class="material-symbols-outlined !text-xl">close</span>
</button>
