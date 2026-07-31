{{--
    Every <head> tag a public page needs to be indexed and to share well.

    One component because the four public pages had drifted: the landing page
    carried a title and a description, privacy and terms carried neither, and
    none of them declared a canonical, an alternate language, or anything a
    social preview reads. A crawler landing on /terms saw an untitled page.

    Deliberately NOT used by the signed-in app layouts - those pages are behind
    auth, and the correct instruction for them is `noindex`, which they get from
    the `robots` prop on the guest layout instead.
--}}

@props([
    'title',
    'description',
    // Canonical URL. Defaults to the current path WITHOUT the query string:
    // `?per_page=50` is the same document and must not be indexed separately.
    'canonical' => null,
    // og:type - 'website' for the landing page, 'article' for the legal pages.
    'type' => 'website',
    'image' => '/og-image.jpg',
    'robots' => 'index, follow, max-image-preview:large, max-snippet:-1',
    // Schema.org graphs, as PHP arrays. One <script> per entry so an invalid
    // one cannot take the others down with it.
    'schemas' => [],
])

@php
    $canonical ??= url()->current();
    $imageUrl = str_starts_with($image, 'http') ? $image : url($image);
    $siteName = __('حوالاتي');
    $locale = app()->getLocale();
@endphp

<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<meta name="robots" content="{{ $robots }}">
<link rel="canonical" href="{{ $canonical }}">

{{--
    Both locales resolve to the SAME url: language is chosen from the session
    (see the locale.switch route), not from the path. Declaring the alternates
    anyway is what tells a crawler the two renderings are one document rather
    than two competing near-duplicates.
--}}
<link rel="alternate" hreflang="ar" href="{{ $canonical }}">
<link rel="alternate" hreflang="en" href="{{ $canonical }}">
<link rel="alternate" hreflang="x-default" href="{{ $canonical }}">

<meta property="og:type" content="{{ $type }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:locale" content="{{ $locale === 'ar' ? 'ar_AR' : 'en_US' }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:image" content="{{ $imageUrl }}">
{{-- Declared dimensions let a preview reserve the right box before the image
     downloads, which is the difference between a card that renders instantly
     and one that pops in. --}}
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ $siteName }} - {{ $description }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $imageUrl }}">

@foreach ($schemas as $schema)
    <script type="application/ld+json">@json($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)</script>
@endforeach
