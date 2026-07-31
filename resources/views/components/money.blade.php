@props(['amount' => 0, 'symbol' => '₪'])

{{-- A currency amount, formatted with its account's own symbol (the accounting
     side is multi-currency, unlike the legacy single-currency ₪ views). Always
     LTR so the digits and symbol read correctly inside an RTL page. --}}
<span {{ $attributes->merge(['class' => 'font-numeral-lg']) }} dir="ltr">{{ number_format((float) $amount, 2) }} {{ $symbol }}</span>
