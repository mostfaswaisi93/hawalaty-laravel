<form method="POST" action="{{ route('banks.destroy', $b->id) }}" id="delete-bank-{{ $b->id }}" class="inline-flex">
    @csrf
    @method('DELETE')
</form>
<button type="button" aria-label="{{ __('حذف') }}"
    @click="confirmOpen = true; confirmMessage = '{{ __('هل أنت متأكد من حذف هذا العنصر؟') }}'; confirmFormId = 'delete-bank-{{ $b->id }}'"
    class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error">
    <span class="material-symbols-outlined !text-xl">delete</span>
</button>
