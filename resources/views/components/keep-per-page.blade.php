@if (request()->filled('per_page'))
    <input type="hidden" name="per_page" value="{{ request()->query('per_page') }}" />
@endif
