{{--
    Dual-rendering pattern used throughout the Angular app (see
    layout.html/dashboard.html and BACKEND_PROMPT.md) - a real `<table>` on
    md+ screens, a stacked card list below md. Both are genuinely rendered
    (not one hidden via JS), so no client-side reflow is needed; only the
    matching `hidden md:block` / `md:hidden` utility pair decides which one
    is visible at a given width.

    Usage:
        <x-table-responsive :headers="['الاسم', 'البريد', 'الدور']">
            <x-slot:table>
                @foreach ($items as $item)
                    <tr class="hover:bg-surface-container-low">
                        <td class="px-4 py-3">{{ $item->name }}</td>
                        ...
                    </tr>
                @endforeach
            </x-slot:table>
            <x-slot:cards>
                @foreach ($items as $item)
                    <div class="tonal-elevation rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                        ...
                    </div>
                @endforeach
            </x-slot:cards>
        </x-table-responsive>

    Callers loop over their collection twice (once per breakpoint's markup)
    - the same thing the Angular templates do with two separate `@for`
    blocks, one inside each container.
--}}
@props(['headers' => []])

<div>
    <div class="hidden overflow-x-auto rounded-2xl border border-outline-variant md:block">
        <table class="w-full text-start">
            @if (count($headers))
                <thead class="bg-surface-container-low">
                    <tr>
                        @foreach ($headers as $header)
                            <th class="px-4 py-3 text-start font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">
                                {{ $header }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
            @endif
            <tbody class="stagger-rows divide-y divide-outline-variant">
                {{ $table }}
            </tbody>
        </table>
    </div>

    <div class="space-y-3 md:hidden">
        {{ $cards }}
    </div>
</div>
