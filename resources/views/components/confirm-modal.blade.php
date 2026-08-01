<div x-show="confirmOpen" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
    <div class="fixed inset-0 bg-black/40" x-show="confirmOpen" x-transition.opacity></div>
    <div x-show="confirmOpen" @keydown.escape.window="confirmOpen = false"
        x-init="$watch('confirmOpen', v => v && $nextTick(() => $el.querySelector('button')?.focus()))"
        role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
        class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-sm overflow-y-auto overflow-x-hidden rounded-3xl bg-surface-container-lowest shadow-2xl">
                <x-modal-close on-close="confirmOpen = false" />
        <div class="px-6 pb-6 pt-8">
            <div class="mb-4 flex justify-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-error-container text-error">
                    <span class="material-symbols-outlined !text-3xl">warning</span>
                </div>
            </div>
            <h3 id="confirm-modal-title" class="mb-2 text-center font-headline-md text-headline-md"
                x-text="$data.confirmTitle || @js(__('تأكيد الحذف'))"></h3>
            <p class="text-center font-body-sm text-on-surface-variant" x-text="confirmMessage"></p>
        </div>

        <div class="flex justify-center gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
            <button type="button" @click="confirmOpen = false"
                class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container" title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</button>
            <button type="button" @click="document.getElementById(confirmFormId).requestSubmit(); confirmOpen = false"
                class="rounded-xl bg-error px-6 py-2.5 font-body-sm font-bold text-on-error shadow-lg transition-all hover:opacity-90 active:scale-95"
                x-text="$data.confirmLabel || @js(__('حذف'))"></button>
        </div>
    </div>
</div>
