{{--
    Shared destructive-action confirmation modal - replaces the browser's
    unstyled native confirm() dialog used everywhere for delete actions.

    Requires the enclosing Alpine scope to expose three properties:
      confirmOpen: false, confirmMessage: '', confirmFormId: null

    Usage per delete action:
      1. Give the delete <form> a unique `id` and drop its
         `onsubmit="return confirm(...)"`.
      2. Change its submit <button> to `type="button"` and
         `@click="confirmOpen = true; confirmMessage = '...'; confirmFormId = '<the form id>'"`.
      3. Include <x-confirm-modal /> once per page, inside the same x-data scope.

    For a non-delete destructive action (e.g. suspending a license), also set
    `confirmTitle` and `confirmLabel` in the same @click to override the delete
    wording. Both are read through `$data` rather than referenced directly so the
    16 plain delete call sites don't have to declare them - an absent property
    reads as undefined instead of throwing a ReferenceError.
--}}
<div x-show="confirmOpen" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
    <div class="fixed inset-0 bg-black/40" x-show="confirmOpen" x-transition.opacity></div>
    <div x-show="confirmOpen" @keydown.escape.window="confirmOpen = false"
        x-init="$watch('confirmOpen', v => v && $nextTick(() => $el.querySelector('button')?.focus()))"
        role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
        class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-sm overflow-y-auto overflow-x-hidden rounded-3xl bg-surface-container-lowest shadow-2xl">
                <x-modal-close on-close="confirmOpen = false" />
        {{-- Body: the icon carries the warning, so this dialog has no separate
             header band - the title sits with the message it belongs to. --}}
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

        {{-- Footer action bar, matching every other modal in the app. --}}
        <div class="flex justify-center gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
            <button type="button" @click="confirmOpen = false"
                class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container" title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</button>
            <button type="button" @click="document.getElementById(confirmFormId).requestSubmit(); confirmOpen = false"
                class="rounded-xl bg-error px-6 py-2.5 font-body-sm font-bold text-on-error shadow-lg transition-all hover:opacity-90 active:scale-95"
                x-text="$data.confirmLabel || @js(__('حذف'))"></button>
        </div>
    </div>
</div>
