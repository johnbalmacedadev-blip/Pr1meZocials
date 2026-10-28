<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#ffffff">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Prime Z') }}">
        {{-- Link previews (Messenger, email, chat apps) read these before any JavaScript runs. --}}
        @php($pageMeta = $meta ?? [])
        <title inertia>{{ $pageMeta['title'] ?? config('app.name', 'Prime Z') }}</title>
        @if (! empty($pageMeta['title']))
            <meta name="description" content="{{ $pageMeta['description'] ?? '' }}">
            <meta property="og:type" content="website">
            <meta property="og:site_name" content="{{ config('app.name', 'Prime Z') }}">
            <meta property="og:title" content="{{ $pageMeta['title'] }}">
            <meta property="og:description" content="{{ $pageMeta['description'] ?? '' }}">
            <meta property="og:url" content="{{ $pageMeta['url'] ?? url()->current() }}">
            <meta property="og:image" content="{{ rtrim(config('app.url'), '/') }}/brand/icon-512.png">
            <meta name="twitter:card" content="summary">
            <meta name="robots" content="noindex">
        @endif
        <link rel="icon" type="image/png" href="{{ rtrim(config('app.url'), '/') }}/brand/favicon.png">
        <link rel="manifest" href="{{ rtrim(config('app.url'), '/') }}/manifest.webmanifest">
        <link rel="apple-touch-icon" href="{{ rtrim(config('app.url'), '/') }}/brand/apple-touch-icon.png">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=ibm-plex-sans:400,500,600,700" rel="stylesheet" />
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body class="bg-zinc-100 text-zinc-900 antialiased">
        @inertia
    </body>
</html>
