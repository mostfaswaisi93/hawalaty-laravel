@props(['paginator', 'perPage' => 10])

@if ($paginator->total() > 0)
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $start = max(1, $current - 2);
        $end = min($last, $current + 2);
    @endphp

    <div class="mt-6 flex flex-col items-center justify-between gap-4 border-t border-outline-variant/50 pt-5 sm:flex-row">
        <x-per-page :perPage="$perPage" />

        <div class="flex items-center gap-3">
            <span class="hidden font-body-sm text-body-sm text-on-surface-variant sm:inline">
                {{ __('عرض') }} {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} {{ __('من') }} {{ $paginator->total() }}
            </span>

            @if ($last > 1)
                <div class="flex max-w-full items-center gap-1 overflow-x-auto">
                    @if ($paginator->onFirstPage())
                        <span class="flex h-9 w-9 items-center justify-center rounded-full text-outline/40"><span class="material-symbols-outlined !text-xl rtl:-scale-x-100">chevron_left</span></span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" aria-label="{{ __('السابق') }}" class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"><span class="material-symbols-outlined !text-xl rtl:-scale-x-100">chevron_left</span></a>
                    @endif

                    @if ($start > 1)
                        <a href="{{ $paginator->url(1) }}" class="flex h-9 min-w-9 items-center justify-center rounded-full px-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container-high">1</a>
                        @if ($start > 2)<span class="px-1 text-outline">…</span>@endif
                    @endif

                    @for ($page = $start; $page <= $end; $page++)
                        @if ($page === $current)
                            <span class="flex h-9 min-w-9 items-center justify-center rounded-full bg-primary px-3 font-body-sm font-bold text-on-primary shadow-sm">{{ $page }}</span>
                        @else
                            <a href="{{ $paginator->url($page) }}" class="flex h-9 min-w-9 items-center justify-center rounded-full px-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container-high">{{ $page }}</a>
                        @endif
                    @endfor

                    @if ($end < $last)
                        @if ($end < $last - 1)<span class="px-1 text-outline">…</span>@endif
                        <a href="{{ $paginator->url($last) }}" class="flex h-9 min-w-9 items-center justify-center rounded-full px-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container-high">{{ $last }}</a>
                    @endif

                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" aria-label="{{ __('التالي') }}" class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"><span class="material-symbols-outlined !text-xl rtl:-scale-x-100">chevron_right</span></a>
                    @else
                        <span class="flex h-9 w-9 items-center justify-center rounded-full text-outline/40"><span class="material-symbols-outlined !text-xl rtl:-scale-x-100">chevron_right</span></span>
                    @endif
                </div>
            @endif
        </div>
    </div>
@endif
