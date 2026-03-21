@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Integrations</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Connect third-party services to enhance your workflow</p>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    {{-- Google Calendar --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 flex items-center justify-center rounded-lg bg-gray-100 dark:bg-slate-700 shrink-0">
                    <svg class="w-5 h-5" viewBox="0 0 24 24">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Google Calendar</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Sync meetings with your Google Calendar</p>
                </div>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                @if($googleConnected)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                        Connected
                    </span>
                    <a href="{{ route('calendar.connections') }}"
                        class="text-sm text-red-500 hover:text-red-700 dark:hover:text-red-400 font-medium">
                        Manage
                    </a>
                @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                        Disconnected
                    </span>
                    <a href="{{ route('calendar.connect', 'google') }}"
                        class="text-sm text-violet-600 dark:text-violet-400 hover:text-violet-700 font-medium">
                        Connect
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Microsoft Calendar --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 flex items-center justify-center rounded-lg bg-gray-100 dark:bg-slate-700 shrink-0">
                    <svg class="w-5 h-5" viewBox="0 0 24 24">
                        <path d="M11.4 24H0V12.6h11.4V24z" fill="#00A4EF"/>
                        <path d="M24 24H12.6V12.6H24V24z" fill="#FFB900"/>
                        <path d="M11.4 11.4H0V0h11.4v11.4z" fill="#F25022"/>
                        <path d="M24 11.4H12.6V0H24v11.4z" fill="#7FBA00"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Microsoft Calendar</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Sync meetings with your Microsoft Outlook Calendar</p>
                </div>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                @if($microsoftConnected)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                        Connected
                    </span>
                    <a href="{{ route('calendar.connections') }}"
                        class="text-sm text-red-500 hover:text-red-700 dark:hover:text-red-400 font-medium">
                        Manage
                    </a>
                @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                        Disconnected
                    </span>
                    <a href="{{ route('calendar.connect', 'microsoft') }}"
                        class="text-sm text-violet-600 dark:text-violet-400 hover:text-violet-700 font-medium">
                        Connect
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Microsoft Teams Webhook --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 flex items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30 shrink-0">
                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M20.625 7.875H14.25V3.375a1.125 1.125 0 0 0-2.25 0v4.5H7.875C6.84 7.875 6 8.715 6 9.75v4.5c0 1.035.84 1.875 1.875 1.875H12v4.5a1.125 1.125 0 0 0 2.25 0v-4.5h4.5c1.035 0 1.875-.84 1.875-1.875v-4.5c0-1.035-.84-1.875-1.875-1.875h-.125z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Microsoft Teams Webhook</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Send meeting notifications to a Teams channel</p>
                </div>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                @if($teamsWebhookConfigured)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                        Configured
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                        Not configured
                    </span>
                @endif
                @if($org)
                    <a href="{{ route('organizations.settings.edit', $org) }}"
                        class="text-sm text-violet-600 dark:text-violet-400 hover:text-violet-700 font-medium">
                        Configure
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
