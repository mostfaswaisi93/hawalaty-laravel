{{--
    "Skip to content", the first thing in the tab order.

    Both shells put a sidebar of ~20 navigation items plus a header ahead of the
    page itself, so reaching the actual content by keyboard meant tabbing past
    around thirty controls - on every single page load. A mouse never notices;
    a keyboard or screen-reader user pays it every time.

    Hidden until focused, so it costs sighted users nothing. It is a real link
    to a real id, not a scripted scroll, so it keeps working if JavaScript does
    not: the browser moves focus to the target itself.
--}}
<a href="#main-content"
    class="sr-only rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg
           focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-[200] focus:outline-none
           focus:ring-2 focus:ring-on-primary focus:ring-offset-2">
    {{ __('تخطَّ إلى المحتوى') }}
</a>
