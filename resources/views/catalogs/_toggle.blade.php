{{-- Active switch. Reuses the update route with only `active` (a partial
     update, allowed because CatalogItemRequest relaxes `name` to `sometimes`
     on PUT) - same shape as banks/_toggle. --}}
{{-- A switch has no glyph to swap, so the in-flight state reads as a pulse and
     a dim rather than the `progress_activity` spinner used on icon buttons.
     Disabling matters more here than elsewhere: the posted value is a hidden
     input computed from the CURRENT state, so a second click before the reload
     would send the same value again and silently undo nothing. --}}
<form method="POST" action="{{ route('catalogs.update', [$catalog, $item->id]) }}" class="inline-flex"
    x-data="{ toggling: false }" @submit="toggling = true">
    @csrf
    @method('PUT')
    <input type="hidden" name="active" value="{{ $item->active ? 0 : 1 }}">
    <button type="submit" role="switch" aria-checked="{{ $item->active ? 'true' : 'false' }}" aria-label="{{ $item->active ? __('إيقاف') : __('تفعيل') }}"
        :disabled="toggling" :class="toggling && 'animate-pulse opacity-60'"
        class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors disabled:cursor-not-allowed {{ $item->active ? 'bg-primary' : 'bg-surface-container-high' }}">
        <span class="absolute top-1 h-4 w-4 rounded-full shadow transition-all {{ $item->active ? 'bg-on-primary start-1' : 'bg-on-surface-variant end-1' }}"></span>
    </button>
</form>
