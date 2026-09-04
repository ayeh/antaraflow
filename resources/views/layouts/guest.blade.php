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

        /* Join-by-code CTA (top-right) */
        .join-cta {
            background: linear-gradient(135deg, color-mix(in srgb, var(--brand-primary) 88%, white), var(--brand-primary));
            box-shadow: 0 4px 14px color-mix(in srgb, var(--brand-primary) 38%, transparent);
            transition: transform .28s cubic-bezier(.2,.8,.2,1), box-shadow .28s ease, filter .2s ease;
            will-change: transform;
        }
        .join-cta:hover {
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 10px 24px color-mix(in srgb, var(--brand-primary) 55%, transparent);
            filter: brightness(1.04);
        }
        .join-cta:active {
            transform: translateY(0) scale(.97);
            box-shadow: 0 3px 10px color-mix(in srgb, var(--brand-primary) 40%, transparent);
        }
        .join-cta:focus-visible {
            outline: 2px solid color-mix(in srgb, var(--brand-primary) 55%, white);
            outline-offset: 2px;
        }
        /* Attention pulse ring */
        .join-cta__glow {
            position: absolute; inset: 0; border-radius: 9999px; pointer-events: none;
            animation: joinPulse 2.8s ease-out infinite;
        }
        @keyframes joinPulse {
            0%   { box-shadow: 0 0 0 0 color-mix(in srgb, var(--brand-primary) 50%, transparent); }
            70%  { box-shadow: 0 0 0 10px color-mix(in srgb, var(--brand-primary) 0%, transparent); }
            100% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--brand-primary) 0%, transparent); }
        }
        /* Shine sweep on hover */
        .join-cta__shine {
            position: absolute; top: 0; left: -75%; width: 45%; height: 100%;
            background: linear-gradient(120deg, transparent, rgba(255,255,255,.55), transparent);
            transform: skewX(-20deg); pointer-events: none;
            transition: left .65s ease;
        }
        .join-cta:hover .join-cta__shine { left: 130%; }
        @media (prefers-reduced-motion: reduce) {
            .join-cta, .join-cta__glow, .join-cta__shine { animation: none !important; transition: none !important; }
            .join-cta:hover { transform: none; }
            .join-cta:hover .join-cta__shine { left: -75%; }
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

    {{-- Top-right controls: join-by-code CTA grouped with the language switcher --}}
    <div class="fixed top-4 right-4 z-50 flex items-center gap-2">
        <a href="{{ route('qr-registration.join') }}"
           class="join-cta group relative inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-white overflow-hidden"
           title="{{ __('Join a Meeting') }}" aria-label="{{ __('Join a Meeting') }}">
            <span class="join-cta__glow" aria-hidden="true"></span>
            <svg class="relative w-4 h-4 shrink-0 transition-transform duration-300 group-hover:rotate-12 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
            </svg>
            <span class="relative">{{ __('Join') }}</span>
            <span class="join-cta__shine" aria-hidden="true"></span>
        </a>
        <x-language-switcher />
    </div>

    <div class="relative w-full max-w-md px-4">
        <div class="text-center mb-8">
            @if($logoSrc)
                <img src="{{ $logoSrc }}" alt="{{ $branding->appName() }}" class="mx-auto h-12 object-contain {{ $resellerOrg ? 'mb-1' : '' }}">
                @if($resellerOrg)
                    <p class="mt-1 text-sm {{ $bgSrc ? 'text-white/70' : 'text-gray-500' }}">{{ __('common.powered_by', ['name' => config('app.name', 'antaraNote')]) }}</p>
                @endif
            @else
                <h1 class="text-2xl font-bold {{ $bgSrc ? 'text-white' : 'text-gray-900' }}">{{ $branding->appName() }}</h1>
                @if($resellerOrg)
                    <p class="mt-1 text-sm {{ $bgSrc ? 'text-white/70' : 'text-gray-500' }}">{{ __('common.powered_by', ['name' => config('app.name', 'antaraNote')]) }}</p>
                @endif
            @endif
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
            @yield('content')
        </div>
    </div>
</body>
</html>
