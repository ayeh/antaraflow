<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? $branding->appName() }}</title>
    @php
        $faviconSrc = $branding->get('favicon_path') ? Storage::url($branding->get('favicon_path')) : ($branding->get('favicon_url') ?: asset('favicon.ico'));
        $logoSrc = $branding->get('logo_path') ? Storage::url($branding->get('logo_path')) : $branding->get('logo_url');
    @endphp
    <link rel="icon" href="{{ $faviconSrc }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root { --brand-primary: {{ $branding->get('primary_color', '#7c3aed') }}; }
        .text-primary { color: var(--brand-primary); }
        .bg-primary { background-color: var(--brand-primary); }
    </style>
</head>
<body class="min-h-screen bg-gray-50 text-gray-800 flex flex-col">
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto max-w-4xl px-4 py-4 flex items-center justify-between">
            <a href="{{ route('about') }}" class="flex items-center gap-2 font-bold text-gray-900">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="{{ $branding->appName() }}" class="h-8 object-contain">
                @else
                    {{ $branding->appName() }}
                @endif
            </a>
            <nav class="flex items-center gap-5 text-sm">
                <a href="{{ route('privacy') }}" class="text-gray-600 hover:text-gray-900">Privacy</a>
                <a href="{{ route('terms') }}" class="text-gray-600 hover:text-gray-900">Terms</a>
                <a href="{{ route('login') }}" class="rounded-lg px-4 py-2 text-white text-sm font-medium bg-primary hover:brightness-90 transition">Sign in</a>
            </nav>
        </div>
    </header>

    <main class="flex-1">
        <div class="mx-auto max-w-3xl px-4 py-10">
            @yield('content')
        </div>
    </main>

    <footer class="border-t border-gray-200 bg-white">
        <div class="mx-auto max-w-4xl px-4 py-6 flex flex-col sm:flex-row items-center justify-between gap-2 text-sm text-gray-500">
            <span>&copy; {{ now()->year }} {{ $branding->appName() }}. All rights reserved.</span>
            <nav class="flex items-center gap-4">
                <a href="{{ route('about') }}" class="hover:text-gray-800">Home</a>
                <a href="{{ route('privacy') }}" class="hover:text-gray-800">Privacy Policy</a>
                <a href="{{ route('terms') }}" class="hover:text-gray-800">Terms of Service</a>
            </nav>
        </div>
    </footer>
</body>
</html>
