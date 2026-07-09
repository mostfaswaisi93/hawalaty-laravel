@props(['size' => 'sm', 'tilt' => false])

<div
    {{ $attributes->class([
        'flex flex-shrink-0 items-center justify-center bg-primary text-on-primary shadow-lg',
        'h-10 w-10 rounded-xl' => $size === 'sm',
        'h-24 w-24 rounded-3xl' => $size === 'lg',
        'rotate-3' => $tilt,
    ]) }}>
    <span
        class="material-symbols-outlined {{ $size === 'lg' ? 'text-5xl' : 'text-2xl' }}"
        style="font-variation-settings: 'FILL' 1">account_balance_wallet</span>
</div>
