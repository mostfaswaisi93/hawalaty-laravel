<form method="POST" action="{{ route('banks.destroy', $b->id) }}" onsubmit="return confirm('{{ __('هل أنت متأكد من حذف هذا العنصر؟') }}')" class="inline-flex">
    @csrf
    @method('DELETE')
    <button type="submit" aria-label="{{ __('حذف') }}"
        class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error">
        <span class="material-symbols-outlined !text-xl">delete</span>
    </button>
</form>
