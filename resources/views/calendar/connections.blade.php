@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-6">Calendar Connections</h1>

    @if(session('success'))
        <div class="mb-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg text-sm">
            {{ session('error') }}
        </div>
    @endif

    <div class="space-y-4">
        {{-- Google Calendar --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19.5 3h-15A1.5 1.5 0 003 4.5v15A1.5 1.5 0 004.5 21h15a1.5 1.5 0 001.5-1.5v-15A1.5 1.5 0 0019.5 3zM12 17.25a5.25 5.25 0 110-10.5 5.25 5.25 0 010 10.5z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-slate-900 dark:text-white">Google Calendar</h3>
                        @php $googleConn = $connections->firstWhere('provider', 'google'); @endphp
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ $googleConn ? 'Connected' : 'Not connected' }}
                        </p>
                    </div>
                </div>
                @if($googleConn)
                    <form method="POST" action="{{ route('calendar.disconnect', $googleConn) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-600 hover:text-red-700 font-medium">Disconnect</button>
                    </form>
                @else
                    <a href="{{ route('calendar.connect', 'google') }}" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors">
                        Connect
                    </a>
                @endif
            </div>
        </div>

        {{-- Outlook Calendar --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-500" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M21 4.5H3A1.5 1.5 0 001.5 6v12A1.5 1.5 0 003 19.5h18a1.5 1.5 0 001.5-1.5V6A1.5 1.5 0 0021 4.5zm-9 9L3 8.25V6l9 5.25L21 6v2.25L12 13.5z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-slate-900 dark:text-white">Outlook Calendar</h3>
                        @php $outlookConn = $connections->firstWhere('provider', 'outlook'); @endphp
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ $outlookConn ? 'Connected' : 'Not connected' }}
                        </p>
                    </div>
                </div>
                @if($outlookConn)
                    <form method="POST" action="{{ route('calendar.disconnect', $outlookConn) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-600 hover:text-red-700 font-medium">Disconnect</button>
                    </form>
                @else
                    <a href="{{ route('calendar.connect', 'outlook') }}" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors">
                        Connect
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
