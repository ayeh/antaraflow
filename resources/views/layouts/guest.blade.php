<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
    <style>
        :root {
            --brand-primary: {{ $branding->get('primary_color', '#7c3aed') }};
        }
        .btn-primary {
            background-color: var(--brand-primary);
        }
        .btn-primary:hover {
            filter: brightness(0.9);
        }
        .link-primary {
            color: var(--brand-primary);
        }
        .focus-primary:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 1px var(--brand-primary);
        }
        @if($branding->get('custom_css'))
        {!! preg_replace('/(?:expression|java\\\?script|@import|ur\\\?l\s*\(|behavior\s*:|data\s*:|\\\\|-moz-binding)/i', '/* blocked */', $branding->get('custom_css')) !!}
        @endif
    </style>
</head>
@php
    $resellerOrg = request()->attributes->get('reseller_organization');
    $logoSrc = $resellerOrg?->logo_path
        ? Storage::url($resellerOrg->logo_path)
        : ($branding->get('logo_path') ? Storage::url($branding->get('logo_path')) : $branding->get('logo_url'));
    $bgSrc = $branding->get('login_background_path')
        ? Storage::url($branding->get('login_background_path'))
        : $branding->get('login_background_url');
@endphp
<body class="min-h-screen flex items-center justify-center"
      @if($bgSrc)
          style="background-image: url('{{ $bgSrc }}'); background-size: cover; background-position: center;"
      @else
          style="background-color: #f9fafb;"
      @endif
>
    @if($bgSrc)
        <div class="absolute inset-0 bg-black/40"></div>
    @endif
    <div class="relative w-full max-w-md px-4">
        <div class="text-center mb-8">
            @if($logoSrc)
                <img src="{{ $logoSrc }}" alt="{{ $branding->appName() }}" class="mx-auto h-12 object-contain {{ $resellerOrg ? 'mb-1' : '' }}">
                @if($resellerOrg)
                    <p class="mt-1 text-sm {{ $bgSrc ? 'text-white/70' : 'text-gray-500' }}">Powered by {{ config('app.name', 'antaraFLOW') }}</p>
                @endif
            @else
                <h1 class="text-2xl font-bold {{ $bgSrc ? 'text-white' : 'text-gray-900' }}">{{ $branding->appName() }}</h1>
                @if($resellerOrg)
                    <p class="mt-1 text-sm {{ $bgSrc ? 'text-white/70' : 'text-gray-500' }}">Powered by {{ config('app.name', 'antaraFLOW') }}</p>
                @endif
            @endif
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
            @yield('content')
        </div>
    </div>
</body>
</html>
