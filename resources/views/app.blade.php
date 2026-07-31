<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        {{-- viewport-fit=cover exposes the notch/home-indicator insets to the
             safe-area-inset-* variables the bottom tab bar pads itself with. --}}
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="manifest" href="/manifest.webmanifest">

        {{-- The manifest carries a single theme_color; these two let the browser
             chrome follow the scheme the way the app itself does. Values track
             --background in app.css. --}}
        <meta name="theme-color" media="(prefers-color-scheme: light)" content="#fbfbfa">
        <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#131314">

        {{-- iOS ignores the manifest for add-to-home-screen and reads these.
             `black-translucent` runs the app under the status bar, which is why
             the viewport opts into the safe-area insets above. --}}
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Suivre">

        {{-- Launch images. Without them iOS shows a white flash on every cold
             start of the installed app, which is the single thing that makes a
             home-screen PWA read as a bookmark rather than an app.

             Safari matches these on CSS pixels and pixel ratio, so each device
             needs its own exact triple — there is no fallback and a near miss
             matches nothing. The filename is derived from the same numbers as
             the query, so the two cannot drift apart. --}}
        @foreach ([[375, 667, 2], [414, 896, 2], [375, 812, 3], [414, 896, 3], [390, 844, 3], [428, 926, 3], [393, 852, 3], [430, 932, 3], [402, 874, 3], [440, 956, 3]] as [$width, $height, $ratio])
            @php($file = ($width * $ratio) . 'x' . ($height * $ratio))
            @php($media = "(device-width: {$width}px) and (device-height: {$height}px) and (-webkit-device-pixel-ratio: {$ratio}) and (orientation: portrait)")
            <link rel="apple-touch-startup-image" media="{{ $media }} and (prefers-color-scheme: light)" href="/splash/splash-{{ $file }}.png">
            <link rel="apple-touch-startup-image" media="{{ $media }} and (prefers-color-scheme: dark)" href="/splash/splash-{{ $file }}-dark.png">
        @endforeach

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
