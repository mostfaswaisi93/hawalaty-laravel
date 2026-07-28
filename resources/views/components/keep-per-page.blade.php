{{--
    Carries the current page size through a GET filter form.

    Without it, filtering silently resets the list to the default page size: the
    <x-per-page> selector re-emits every active filter when it submits, but the
    filter forms never did the same in reverse. An admin who chose 100 per page
    and then typed a search got 10 back, with nothing to explain why.

    Emits nothing when the request carries no `per_page`, so the URL stays clean
    for anyone who never touched the selector.

    Usage: drop <x-keep-per-page /> inside any GET filter <form>.
--}}
@if (request()->filled('per_page'))
    <input type="hidden" name="per_page" value="{{ request()->query('per_page') }}" />
@endif
