<form method="POST" action="{{ route('banks.update', $b->id) }}" class="inline-flex">
    @csrf
    @method('PUT')
    <input type="hidden" name="active" value="{{ $b->active ? 0 : 1 }}">
    <button type="submit" role="switch" aria-checked="{{ $b->active ? 'true' : 'false' }}" aria-label="{{ $b->active ? __('إيقاف') : __('تفعيل') }}"
        class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors {{ $b->active ? 'bg-primary' : 'bg-surface-container-high' }}">
        <span class="absolute top-1 h-4 w-4 rounded-full bg-white shadow transition-all {{ $b->active ? 'start-1' : 'end-1' }}"></span>
    </button>
</form>
