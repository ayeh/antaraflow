<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? $branding->appName() }}</title>
    <x-pwa-meta />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if($branding->get('custom_css'))
    <style>{!! $branding->get('custom_css') !!}</style>
    @endif
</head>
<body class="h-full bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors">
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

        <div :class="sidebarCollapsed ? 'md:ml-14' : 'md:ml-56'" class="flex flex-col min-h-screen transition-all duration-300 ease-in-out">
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
</body>
</html>
