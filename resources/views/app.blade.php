<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Per-request CSP nonce, for the style tags Inertia injects. --}}
    <meta name="csp-nonce" content="{{ Vite::cspNonce() }}">
    <meta name="theme-color" content="#0b3d6e">
    <script src="/js/theme.js" nonce="{{ Vite::cspNonce() }}"></script>
    {{-- The published favicon (Admin → Branding), else the built-in one. --}}
    <link rel="icon" href="{{ $page['props']['branding']['favicon'] ?? '/favicon.ico' }}">
    <title inertia>{{ $page['props']['seo']['title'] ?? config('app.name') }}</title>
    @isset($page['props']['seo'])
        @php($seo = $page['props']['seo'])
        {{-- Server-rendered so crawlers and link previews see it without running JavaScript. --}}
        <meta name="description" content="{{ $seo['description'] }}">
        <link rel="canonical" href="{{ $seo['canonical'] }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="ABV-IIITM Alumni Connect">
        <meta property="og:title" content="{{ $seo['title'] }}">
        <meta property="og:description" content="{{ $seo['description'] }}">
        <meta property="og:url" content="{{ $seo['canonical'] }}">
        <meta property="og:image" content="{{ $seo['image'] }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $seo['title'] }}">
        <meta name="twitter:description" content="{{ $seo['description'] }}">
        <meta name="twitter:image" content="{{ $seo['image'] }}">
        {{-- Structured data is a non-executable data block; it still carries the nonce. --}}
        <script type="application/ld+json" nonce="{{ Vite::cspNonce() }}">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'ABV-IIITM Gwalior Alumni Association',
            'url' => $seo['canonical'],
            'logo' => $seo['image'],
            'parentOrganization' => ['@type' => 'CollegeOrUniversity', 'name' => 'ABV-Indian Institute of Information Technology and Management, Gwalior', 'url' => 'https://www.iiitm.ac.in'],
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endisset
    @vite('resources/js/app.ts')
    <x-inertia::head />
</head>
<body class="h-full">
    <x-inertia::app />
</body>
</html>
