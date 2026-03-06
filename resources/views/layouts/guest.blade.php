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
            @php $resellerOrg = request()->attributes->get('reseller_organization'); @endphp
            @if($resellerOrg && $resellerOrg->logo_path)
                <img src="{{ Storage::url($resellerOrg->logo_path) }}" alt="{{ $branding->appName() }}" class="mx-auto h-12 mb-3">
            @elseif($branding->get('logo_url'))
                <img src="{{ $branding->get('logo_url') }}" alt="{{ $branding->appName() }}" class="mx-auto h-12 mb-3">
            @endif
            <h1 class="text-2xl font-bold text-gray-900">{{ $branding->appName() }}</h1>
            @if($resellerOrg)
                <p class="mt-1 text-sm text-gray-500">Powered by {{ config('app.name', 'antaraFLOW') }}</p>
            @endif
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
            @yield('content')
        </div>
    </div>
</body>
</html>
