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
