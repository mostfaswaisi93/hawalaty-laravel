{{--
    success/error are transient confirmations, so they float as a dismissible
    toast pinned to the top-centre of the viewport and auto-close. Validation
    errors deliberately stay inline: they refer to specific fields the user has
    to go back and fix, so they must not disappear on a timer.

    `fixed` here is safe because this component renders from the layout, outside
    the `.page-enter` wrapper - see the note on .page-enter in app.css about
    transformed ancestors capturing fixed positioning.
--}}
@if (session('success') || session('error'))
    @php
        $isSuccess = (bool) session('success');
        $flash = $isSuccess ? session('success') : session('error');
    @endphp
    <div
        x-data="{ show: false }"
        x-init="$nextTick(() => show = true); setTimeout(() => show = false, 4500)"
        x-show="show"
        x-cloak
        style="display:none"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 -translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-4"
        class="fixed inset-x-0 top-6 z-[110] mx-auto flex w-fit max-w-[calc(100vw-2rem)] items-center gap-3 rounded-2xl border px-5 py-3 shadow-2xl backdrop-blur
            {{ $isSuccess
                ? 'border-success/30 bg-success-container/95 text-success dark:bg-surface-container-high/95'
                : 'border-error/30 bg-error-container/95 text-on-error-container dark:bg-surface-container-high/95' }}"
        role="status"
        aria-live="polite">
        <span class="material-symbols-outlined flex-shrink-0 {{ $isSuccess ? 'text-success' : 'text-error' }}">
            {{ $isSuccess ? 'check_circle' : 'error' }}
        </span>
        <p class="font-body-sm text-body-sm font-bold">{{ $flash }}</p>
        <button type="button" @click="show = false"
            class="-me-1 flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full opacity-60 transition-opacity hover:opacity-100"
            aria-label="{{ __('إغلاق') }}" title="{{ __('إغلاق') }}">
            <span class="material-symbols-outlined !text-lg">close</span>
        </button>
    </div>
@endif

@if ($errors->any() && ! $errors->has('login'))
    <div class="mb-6 flex items-start gap-3 rounded-xl border border-error/20 bg-error-container/40 p-4 text-on-error-container">
        <span class="material-symbols-outlined text-error">error</span>
        <div class="font-body-sm text-body-sm">
            <p class="font-bold">{{ __('يرجى تصحيح الأخطاء التالية:') }}</p>
            <ul class="mt-1 list-disc ps-5">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

@error('login')
    <div class="mb-6 flex items-start gap-3 rounded-xl border border-error/20 bg-error-container/40 p-4 text-on-error-container">
        <span class="material-symbols-outlined text-error">error</span>
        <p class="font-body-sm text-body-sm">{{ $message }}</p>
    </div>
@enderror
