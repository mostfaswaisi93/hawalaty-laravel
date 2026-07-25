<form method="POST" action="{{ route('banks.update', $b->id) }}" class="inline-flex">
    @csrf
    @method('PUT')
    <input type="hidden" name="active" value="{{ $b->active ? 0 : 1 }}">
    <button type="submit" role="switch" aria-checked="{{ $b->active ? 'true' : 'false' }}" aria-label="{{ $b->active ? __('إيقاف') : __('تفعيل') }}"
        class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors {{ $b->active ? 'bg-primary' : 'bg-surface-container-high' }}">
        {{-- Knob color is tokenised, not bg-white: the active track is bg-primary, which is pale
             lavender in dark mode — a white knob all but disappears and the switch reads as off. --}}
        <span class="absolute top-1 h-4 w-4 rounded-full shadow transition-all {{ $b->active ? 'bg-on-primary start-1' : 'bg-on-surface-variant end-1' }}"></span>
    </button>
</form>
