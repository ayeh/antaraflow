<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-900">{{ $branding->appName() }}</h1>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
            @yield('content')
        </div>
    </div>
</body>
</html>
