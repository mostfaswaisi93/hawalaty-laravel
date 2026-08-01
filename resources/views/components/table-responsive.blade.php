@props(['headers' => []])

<div>
    <div class="hidden overflow-x-auto rounded-2xl border border-outline-variant md:block">
        <table class="w-full text-start">
            @if (count($headers))
                <thead class="bg-surface-container-low">
                    <tr>
                        @foreach ($headers as $header)
                            @php
                                $label = is_array($header) ? ($header['label'] ?? '') : $header;
                                $align = is_array($header) ? ($header['align'] ?? 'start') : 'start';
                            @endphp
                            <th @class([
                                'px-4 py-3 font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant',
                                'text-start' => $align === 'start',
                                'text-end' => $align === 'end',
                                'text-center' => $align === 'center',
                            ])>
                                {{ $label }}
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
