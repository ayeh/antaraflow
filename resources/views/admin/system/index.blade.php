@extends('admin.layouts.app')

@section('title', 'System Monitoring')
@section('page-title', 'System Monitoring')

@section('breadcrumbs')
    <nav class="text-sm text-slate-400 mb-1">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-white">Dashboard</a>
        <span class="mx-1">/</span>
        <span class="text-slate-200">System Monitoring</span>
    </nav>
@endsection

@section('content')
    {{-- System Info Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
        {{-- PHP Version --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-blue-600/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                    </svg>
                </div>
                <span class="text-sm text-slate-400">PHP Version</span>
            </div>
            <p class="text-2xl font-bold text-white">{{ $systemInfo['php_version'] }}</p>
        </div>

        {{-- Laravel Version --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-red-600/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <span class="text-sm text-slate-400">Laravel Version</span>
            </div>
            <p class="text-2xl font-bold text-white">{{ $systemInfo['laravel_version'] }}</p>
        </div>

        {{-- Database Driver --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-600/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                    </svg>
                </div>
                <span class="text-sm text-slate-400">Database Driver</span>
            </div>
            <p class="text-2xl font-bold text-white">{{ $systemInfo['database'] }}</p>
        </div>

        {{-- Cache Driver --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-amber-600/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <span class="text-sm text-slate-400">Cache Driver</span>
            </div>
            <p class="text-2xl font-bold text-white">{{ $systemInfo['cache_driver'] }}</p>
        </div>

        {{-- Queue Driver --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-violet-600/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
                <span class="text-sm text-slate-400">Queue Driver</span>
            </div>
            <p class="text-2xl font-bold text-white">{{ $systemInfo['queue_driver'] }}</p>
        </div>
    </div>

    {{-- Disk Usage --}}
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 mb-8">
        <h3 class="text-sm font-semibold text-slate-200 mb-4">Disk Usage</h3>
        <div class="flex items-center gap-4 mb-2">
            <div class="flex-1 bg-slate-700 rounded-full h-4 overflow-hidden">
                <div class="h-full rounded-full transition-all {{ $diskUsagePercent > 90 ? 'bg-red-500' : ($diskUsagePercent > 75 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                     style="width: {{ $diskUsagePercent }}%"></div>
            </div>
            <span class="text-sm font-medium text-white whitespace-nowrap">{{ $diskUsagePercent }}%</span>
        </div>
        <p class="text-sm text-slate-400">
            {{ number_format($diskUsed / 1073741824, 1) }} GB used of {{ number_format($diskTotal / 1073741824, 1) }} GB
        </p>
    </div>

    {{-- Queue Status --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-sm font-semibold text-slate-200 mb-2">Queue Status</h3>
            <div class="flex items-center gap-4">
                <div>
                    <p class="text-3xl font-bold text-white">{{ number_format($pendingJobs) }}</p>
                    <p class="text-sm text-slate-400 mt-1">Pending Jobs</p>
                </div>
            </div>
        </div>

        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-sm font-semibold text-slate-200 mb-2">Failed Jobs</h3>
            <div class="flex items-center gap-4">
                <div>
                    <p class="text-3xl font-bold {{ $failedJobs->count() > 0 ? 'text-red-400' : 'text-white' }}">{{ number_format($failedJobs->count()) }}</p>
                    <p class="text-sm text-slate-400 mt-1">Total Failed</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Failed Jobs Table --}}
    @if($failedJobs->count() > 0)
        <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-slate-700">
                <h3 class="text-sm font-semibold text-slate-200">Failed Jobs</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead>
                        <tr class="bg-slate-700/50 text-slate-300 text-xs uppercase tracking-wider">
                            <th class="px-6 py-3">ID</th>
                            <th class="px-6 py-3">Queue</th>
                            <th class="px-6 py-3">Payload</th>
                            <th class="px-6 py-3">Exception</th>
                            <th class="px-6 py-3">Failed At</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        @foreach($failedJobs as $job)
                            <tr class="hover:bg-slate-700/30 transition-colors">
                                <td class="px-6 py-3 text-white font-medium">{{ $job->id }}</td>
                                <td class="px-6 py-3 text-slate-300">{{ $job->queue }}</td>
                                <td class="px-6 py-3 text-slate-400 max-w-xs truncate" title="{{ $job->payload }}">
                                    {{ \Illuminate\Support\Str::limit($job->payload, 100) }}
                                </td>
                                <td class="px-6 py-3 text-red-400 max-w-xs truncate" title="{{ $job->exception }}">
                                    {{ \Illuminate\Support\Str::limit($job->exception, 100) }}
                                </td>
                                <td class="px-6 py-3 text-slate-400 whitespace-nowrap">{{ $job->failed_at }}</td>
                                <td class="px-6 py-3 text-right whitespace-nowrap">
                                    <form method="POST" action="{{ route('admin.system.retry-job', $job->id) }}" class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="text-blue-400 hover:text-blue-300 text-sm font-medium transition-colors">
                                            Retry
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.system.delete-job', $job->id) }}" class="inline ml-3"
                                          onsubmit="return confirm('Are you sure you want to delete this failed job?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="text-red-400 hover:text-red-300 text-sm font-medium transition-colors">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Recent Errors --}}
    @if($recentErrors->count() > 0)
        <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-700">
                <h3 class="text-sm font-semibold text-slate-200">Recent Errors</h3>
                <p class="text-xs text-slate-400 mt-1">Last {{ $recentErrors->count() }} error/critical entries from the application log</p>
            </div>
            <div class="max-h-96 overflow-y-auto">
                <div class="divide-y divide-slate-700">
                    @foreach($recentErrors as $error)
                        @php
                            $timestamp = '';
                            $message = trim($error);
                            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}[^\]]*)\]\s*(.+)$/', $message, $matches)) {
                                $timestamp = $matches[1];
                                $message = $matches[2];
                            }
                        @endphp
                        <div class="px-6 py-3 hover:bg-slate-700/30 transition-colors">
                            @if($timestamp)
                                <span class="text-xs text-slate-500 font-mono">{{ $timestamp }}</span>
                            @endif
                            <p class="text-sm text-red-400 font-mono break-all mt-0.5">{{ \Illuminate\Support\Str::limit($message, 300) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <div class="text-center py-4">
                <svg class="w-12 h-12 text-emerald-500 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-slate-300 font-medium">No Recent Errors</p>
                <p class="text-sm text-slate-400 mt-1">The application log is clean.</p>
            </div>
        </div>
    @endif
@endsection
