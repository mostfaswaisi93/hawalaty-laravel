{{--
    Shared across the tenant, admin and guest layouts so the credit line only
    ever has to change in one place. The year is derived, not hard-coded.
--}}
<footer class="mt-auto border-t border-outline-variant/40 px-inline-lg py-5">
    <div class="mx-auto flex max-w-[1680px] flex-col items-center justify-between gap-3 sm:flex-row">
        <p class="font-body-sm text-body-sm text-outline">
            © {{ date('Y') }}
            <span class="font-bold text-on-surface-variant">{{ __('حوالاتي') }}</span>
            · {{ __('جميع الحقوق محفوظة') }}
        </p>

        <div class="flex items-center gap-3">
            <span class="flex items-center gap-1.5 font-body-sm text-body-sm text-outline">
                {{ __('صُنع بحب') }}
                {{-- FILL 1 so the heart renders solid rather than outlined. --}}
                <span class="material-symbols-outlined !text-base text-error" style="font-variation-settings: 'FILL' 1" aria-hidden="true">favorite</span>
                {{ __('بواسطة') }}
            </span>

            <a href="https://github.com/mostfaswaisi93" target="_blank" rel="noopener noreferrer"
                class="flex items-center gap-1.5 rounded-lg px-2 py-1 font-body-sm text-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                aria-label="{{ __('حساب GitHub للمطوّر') }}" title="{{ __('حساب GitHub للمطوّر') }}">
                <svg viewBox="0 0 16 16" class="h-4 w-4 flex-shrink-0" fill="currentColor" aria-hidden="true">
                    <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8Z" />
                </svg>
                <span dir="ltr">mostfaswaisi93</span>
            </a>
        </div>
    </div>
</footer>
