@props(['amount' => 0, 'symbol' => '₪'])

<span {{ $attributes->merge(['class' => 'font-numeral-lg']) }} dir="ltr">{{ number_format((float) $amount, 2) }} {{ $symbol }}</span>
