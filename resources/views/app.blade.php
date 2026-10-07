<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Per-request CSP nonce, for the style tags Inertia injects. --}}
    <meta name="csp-nonce" content="{{ Vite::cspNonce() }}">
    <meta name="theme-color" content="#0b3d6e">
    <script src="/js/theme.js" nonce="{{ Vite::cspNonce() }}"></script>
    <title inertia>{{ config('app.name') }}</title>
    @vite('resources/js/app.ts')
    <x-inertia::head />
</head>
<body class="h-full">
    <x-inertia::app />
</body>
</html>
