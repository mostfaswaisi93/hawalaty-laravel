{{-- Supported-currency chips for a bank / wallet / cash box row. --}}
@if ($item->currencies->isEmpty())
    <span class="font-body-sm text-outline">{{ __('لا عملات') }}</span>
@else
    <div class="flex flex-wrap gap-1">
        @foreach ($item->currencies as $cur)
            <span class="inline-flex items-center gap-1 rounded-full bg-surface-container-high px-2 py-0.5 text-[11px] font-bold text-on-surface-variant" title="{{ $cur->name }}">
                <span dir="ltr">{{ $cur->symbol }}</span>
                <span>{{ $cur->name }}</span>
            </span>
        @endforeach
    </div>
@endif
