<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>{{ config('app.name', 'Prime Z') }}</title>
        <link rel="icon" type="image/png" href="{{ rtrim(config('app.url'), '/') }}/brand/favicon.png">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=ibm-plex-sans:400,500,600,700" rel="stylesheet" />
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body class="bg-zinc-100 text-zinc-900 antialiased">
        @inertia
        <script>
            window.addEventListener('load', function () {
                var app = document.getElementById('app');
                if (!app || app.children.length) {
                    return;
                }
                app.innerHTML = '<div style="max-width:32rem;margin:3rem auto;padding:1.5rem;font-family:system-ui,sans-serif;background:#fff;border:1px solid #e4e4e7;border-radius:1rem">'
                    + '<h1 style="margin:0 0 .5rem;font-size:1.25rem">Prime Z did not load</h1>'
                    + '<p style="margin:0;color:#52525b;line-height:1.45">The page HTML loaded, but the JavaScript/CSS assets did not. Pull the latest <code>main</code> from GitHub, then hard-refresh. Assets should open at <code>/public/build/</code> or <code>/build/</code>.</p>'
                    + '</div>';
            });
        </script>
    </body>
</html>
