@props(['onClose'])

<button type="button" @click="hideTip(); {{ $onClose }}"
    @mouseenter="showTipBelow($el, @js(__('إغلاق')))" @mouseleave="hideTip()"
    @focus="showTipBelow($el, @js(__('إغلاق')))" @blur="hideTip()"
    class="absolute top-3 z-10 flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-error focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary end-4"
    aria-label="{{ __('إغلاق') }}" title="{{ __('إغلاق') }}">
    <span class="material-symbols-outlined !text-xl">close</span>
</button>
