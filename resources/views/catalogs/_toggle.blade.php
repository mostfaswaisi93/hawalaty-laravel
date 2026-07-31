{{-- Active switch. Reuses the update route with only `active` (a partial
     update, allowed because CatalogItemRequest relaxes `name` to `sometimes`
     on PUT) - same shape as banks/_toggle. --}}
<form method="POST" action="{{ route('catalogs.update', [$catalog, $item->id]) }}" class="inline-flex">
    @csrf
    @method('PUT')
    <input type="hidden" name="active" value="{{ $item->active ? 0 : 1 }}">
    <button type="submit" role="switch" aria-checked="{{ $item->active ? 'true' : 'false' }}" aria-label="{{ $item->active ? __('إيقاف') : __('تفعيل') }}"
        class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors {{ $item->active ? 'bg-primary' : 'bg-surface-container-high' }}">
        <span class="absolute top-1 h-4 w-4 rounded-full shadow transition-all {{ $item->active ? 'bg-on-primary start-1' : 'bg-on-surface-variant end-1' }}"></span>
    </button>
</form>
