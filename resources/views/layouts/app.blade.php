<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? $branding->appName() }}</title>
    @php
        $faviconSrc = $branding->get('favicon_path') ? Storage::url($branding->get('favicon_path')) : ($branding->get('favicon_url') ?: asset('favicon.ico'));
    @endphp
    <link rel="icon" href="{{ $faviconSrc }}">
    <x-pwa-meta />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="{{ asset('js/celebration.js') }}" defer></script>
    <style>
        :root {
            --brand-primary: {{ $branding->get('primary_color', '#7c3aed') }};
            --brand-p50:  color-mix(in srgb, var(--brand-primary)  5%, white);
            --brand-p100: color-mix(in srgb, var(--brand-primary) 10%, white);
            --brand-p200: color-mix(in srgb, var(--brand-primary) 20%, white);
            --brand-p300: color-mix(in srgb, var(--brand-primary) 40%, white);
            --brand-p400: color-mix(in srgb, var(--brand-primary) 60%, white);
            --brand-p500: color-mix(in srgb, var(--brand-primary) 80%, white);
            --brand-p600: var(--brand-primary);
            --brand-p700: color-mix(in srgb, var(--brand-primary) 85%, black);
            --brand-p800: color-mix(in srgb, var(--brand-primary) 70%, black);
            --brand-p900: color-mix(in srgb, var(--brand-primary) 15%, #0f172a);
        }
        /* Background */
        .bg-violet-50  { background-color: var(--brand-p50)  !important; }
        .bg-violet-100 { background-color: var(--brand-p100) !important; }
        .bg-violet-200 { background-color: var(--brand-p200) !important; }
        .bg-violet-400 { background-color: var(--brand-p400) !important; }
        .bg-violet-500 { background-color: var(--brand-p500) !important; }
        .bg-violet-600 { background-color: var(--brand-p600) !important; }
        .bg-violet-700 { background-color: var(--brand-p700) !important; }
        .bg-violet-800 { background-color: var(--brand-p800) !important; }
        .bg-violet-900 { background-color: var(--brand-p900) !important; }
        /* Hover backgrounds */
        .hover\:bg-violet-50:hover  { background-color: var(--brand-p50)  !important; }
        .hover\:bg-violet-100:hover { background-color: var(--brand-p100) !important; }
        .hover\:bg-violet-600:hover { background-color: var(--brand-p600) !important; }
        .hover\:bg-violet-700:hover { background-color: var(--brand-p700) !important; }
        /* Text */
        .text-violet-400 { color: var(--brand-p400) !important; }
        .text-violet-500 { color: var(--brand-p500) !important; }
        .text-violet-600 { color: var(--brand-p600) !important; }
        .text-violet-700 { color: var(--brand-p700) !important; }
        .hover\:text-violet-600:hover { color: var(--brand-p600) !important; }
        .hover\:text-violet-700:hover { color: var(--brand-p700) !important; }
        /* Dark mode text */
        .dark .dark\:text-violet-400 { color: var(--brand-p400) !important; }
        .dark .dark\:text-violet-300 { color: var(--brand-p300) !important; }
        /* Border */
        .border-violet-200 { border-color: var(--brand-p200) !important; }
        .border-violet-300 { border-color: var(--brand-p300) !important; }
        .border-violet-400 { border-color: var(--brand-p400) !important; }
        .border-violet-500 { border-color: var(--brand-p500) !important; }
        .border-violet-600 { border-color: var(--brand-p600) !important; }
        .hover\:border-violet-500:hover { border-color: var(--brand-p500) !important; }
        /* Ring / focus */
        .ring-violet-500 { --tw-ring-color: var(--brand-p500) !important; }
        .focus\:ring-violet-500:focus  { --tw-ring-color: var(--brand-p500) !important; }
        .focus\:border-violet-500:focus { border-color: var(--brand-p500) !important; }
        /* Dark mode bg */
        .dark .dark\:bg-violet-900\/10 { background-color: color-mix(in srgb, var(--brand-primary) 10%, transparent) !important; }
        .dark .dark\:bg-violet-500\/20 { background-color: color-mix(in srgb, var(--brand-primary) 20%, transparent) !important; }
        .dark .dark\:border-violet-500 { border-color: var(--brand-p500) !important; }
    </style>
    @if($branding->get('custom_css'))
    <style>{!! preg_replace('/(?:expression|java\\\?script|@import|ur\\\?l\s*\(|behavior\s*:|data\s*:|\\\\|-moz-binding)/i', '/* blocked */', $branding->get('custom_css')) !!}</style>
    @endif
</head>
<body class="h-full bg-slate-200 dark:bg-slate-950 text-slate-900 dark:text-slate-100 transition-colors">
    <div
        x-data="appState"
        x-init="recentMeetings = {!! \Illuminate\Support\Js::from($recentMeetings ?? []) !!}"
        class="min-h-full"
    >
        {{-- Sidebar (desktop only) --}}
        <div class="hidden md:block">
            @include('layouts.partials.sidebar')
            @include('layouts.partials.settings-flyout')
        </div>

        {{-- Offline Indicator --}}
        <x-offline-indicator />

        <div :class="sidebarCollapsed ? 'md:ml-[5rem]' : 'md:ml-[15.5rem]'" class="flex flex-col min-h-screen bg-slate-50 dark:bg-slate-900 md:mt-3 md:[clip-path:inset(0_0_0_0_round_1rem_0_0_0)] transition-all duration-300 ease-in-out">
            @include('layouts.partials.header')

            @if(session('success'))
                <div class="mx-6 mt-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mx-6 mt-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <main class="flex-1 p-6">
                @yield('content')
            </main>
        </div>

        {{-- Command Palette (all screen sizes) --}}
        @include('layouts.partials.command-palette')

        {{-- FAB (all screen sizes) --}}
        @include('layouts.partials.fab')

        {{-- Mobile Bottom Nav --}}
        <div class="md:hidden">
            @include('layouts.partials.mobile-bottom-nav')
        </div>
    </div>

    {{-- Celebration FX: trigger on action item completed --}}
    <script>
        window.addEventListener('action-item-status-changed', function (e) {
            if (e.detail && e.detail.status === 'completed' && typeof celebrate === 'function') {
                celebrate();
            }
        });
    </script>
</body>
</html>
