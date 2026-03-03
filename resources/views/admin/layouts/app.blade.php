<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — antaraFLOW Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-800 text-slate-100">
    <div class="flex h-full">
        {{-- Sidebar --}}
        <aside class="w-64 bg-slate-900 border-r border-slate-700 flex flex-col fixed inset-y-0">
            {{-- Logo area --}}
            <div class="p-6 border-b border-slate-700">
                <h1 class="text-lg font-bold text-white">antaraFLOW</h1>
                <p class="text-xs text-slate-400 mt-1">Super Admin</p>
            </div>

            {{-- Navigation --}}
            <nav class="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
                @php
                    $navItems = [
                        [
                            'label' => 'Dashboard',
                            'route' => 'admin.dashboard',
                            'active' => 'admin.dashboard',
                            'icon' => 'chart-bar',
                        ],
                        [
                            'label' => 'Subscription Plans',
                            'route' => 'admin.plans.index',
                            'active' => 'admin.plans.*',
                            'icon' => 'credit-card',
                        ],
                        [
                            'label' => 'Users',
                            'route' => 'admin.users.index',
                            'active' => 'admin.users.*',
                            'icon' => 'users',
                        ],
                        [
                            'label' => 'Organizations',
                            'route' => 'admin.organizations.index',
                            'active' => 'admin.organizations.*',
                            'icon' => 'building-office',
                        ],
                        [
                            'label' => 'Branding',
                            'route' => 'admin.branding.index',
                            'active' => 'admin.branding.*',
                            'icon' => 'paint-brush',
                        ],
                        [
                            'label' => 'SMTP',
                            'route' => 'admin.smtp.index',
                            'active' => 'admin.smtp.*',
                            'icon' => 'envelope',
                        ],
                        [
                            'label' => 'Email Templates',
                            'route' => 'admin.email-templates.index',
                            'active' => 'admin.email-templates.*',
                            'icon' => 'document-text',
                        ],
                        [
                            'label' => 'System',
                            'route' => 'admin.system.index',
                            'active' => 'admin.system.*',
                            'icon' => 'cog',
                        ],
                    ];
                @endphp

                @foreach($navItems as $item)
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-lg transition-colors {{ request()->routeIs($item['active']) ? 'bg-slate-700/50 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
                        @include('admin.layouts.partials.icons.' . $item['icon'])
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            {{-- Admin info + logout at bottom --}}
            <div class="p-4 border-t border-slate-700">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-300 truncate">{{ auth('admin')->user()->name }}</span>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-slate-400 hover:text-red-400 transition-colors">Logout</button>
                    </form>
                </div>
            </div>
        </aside>

        {{-- Main content --}}
        <div class="flex-1 ml-64 flex flex-col min-h-screen">
            {{-- Top bar with breadcrumbs --}}
            <header class="bg-slate-800 border-b border-slate-700 px-8 py-4">
                @yield('breadcrumbs')
                <h2 class="text-xl font-semibold text-white">@yield('page-title')</h2>
            </header>

            {{-- Flash messages --}}
            @if(session('success'))
                <div class="mx-8 mt-4 bg-green-900/20 border border-green-800 text-green-300 px-4 py-3 rounded-lg text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mx-8 mt-4 bg-red-900/20 border border-red-800 text-red-300 px-4 py-3 rounded-lg text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <main class="flex-1 p-8">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
