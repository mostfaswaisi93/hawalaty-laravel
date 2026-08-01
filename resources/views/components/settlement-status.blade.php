@props(['status'])

@php
    $value = $status instanceof \App\Enums\SettlementStatus ? $status->value : (string) $status;

    $isConfirmed = $value === \App\Enums\SettlementStatus::Confirmed->value;
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded-full px-3 py-1 font-body-sm text-body-sm font-bold',
    $isConfirmed ? 'bg-success-container/20 text-success' : 'bg-secondary-container/20 text-secondary',
]) }}>
    <span class="material-symbols-outlined !text-base">{{ $isConfirmed ? 'lock' : 'edit_note' }}</span>
    {{ $isConfirmed ? __('مؤكّدة') : __('مسودّة') }}
</span>
