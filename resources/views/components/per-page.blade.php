{{--
    Per-page selector for paginated tables. Auto-submits on change and
    preserves the current filters (everything except `per_page` and `page`),
    resetting to page 1. Options: 10 / 50 / 100.
--}}
@props(['perPage' => 10, 'options' => [10, 50, 100]])

<form method="GET" class="flex items-center gap-2">
    @foreach (request()->except(['per_page', 'page']) as $key => $val)
        @if (is_array($val))
            @foreach ($val as $v)
                <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
            @endforeach
        @else
            <input type="hidden" name="{{ $key }}" value="{{ $val }}">
        @endif
    @endforeach

    <label class="font-body-sm text-body-sm text-on-surface-variant">{{ __('لكل صفحة') }}</label>
    <select name="per_page" onchange="this.form.submit()"
        class="rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2 font-body-sm font-bold text-on-surface outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
        @foreach ($options as $opt)
            <option value="{{ $opt }}" @selected((int) $perPage === (int) $opt)>{{ $opt }}</option>
        @endforeach
    </select>
</form>
