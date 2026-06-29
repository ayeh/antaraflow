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

    {{-- Flash messages --}}

    {{-- ── SECTION 1: System Info ── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 rounded-lg bg-blue-600/20 flex items-center justify-center">
                    <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                </div>
                <span class="text-xs text-slate-400">PHP</span>
            </div>
            <p class="text-xl font-bold text-white">{{ $systemInfo['php_version'] }}</p>
        </div>

        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 rounded-lg bg-red-600/20 flex items-center justify-center">
                    <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <span class="text-xs text-slate-400">Laravel</span>
            </div>
            <p class="text-xl font-bold text-white">{{ $systemInfo['laravel_version'] }}</p>
        </div>

        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 rounded-lg bg-emerald-600/20 flex items-center justify-center">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
                </div>
                <span class="text-xs text-slate-400">Database</span>
            </div>
            <p class="text-xl font-bold text-white capitalize">{{ $systemInfo['database'] }}</p>
        </div>

        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 rounded-lg bg-amber-600/20 flex items-center justify-center">
                    <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                <span class="text-xs text-slate-400">Cache</span>
            </div>
            <p class="text-xl font-bold text-white capitalize">{{ $systemInfo['cache_driver'] }}</p>
        </div>

        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 rounded-lg bg-violet-600/20 flex items-center justify-center">
                    <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
                <span class="text-xs text-slate-400">Queue</span>
            </div>
            <p class="text-xl font-bold text-white capitalize">{{ $systemInfo['queue_driver'] }}</p>
        </div>
    </div>

    {{-- ── SECTION 2: Status Cards (Disk + SMTP + Queue + Failed) ── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">

        {{-- Disk Usage --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Disk Usage</span>
                <span class="text-xs font-bold {{ $diskUsagePercent > 90 ? 'text-red-400' : ($diskUsagePercent > 75 ? 'text-amber-400' : 'text-emerald-400') }}">{{ $diskUsagePercent }}%</span>
            </div>
            <div class="w-full bg-slate-700 rounded-full h-2 mb-3">
                <div class="h-full rounded-full {{ $diskUsagePercent > 90 ? 'bg-red-500' : ($diskUsagePercent > 75 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                     style="width: {{ $diskUsagePercent }}%"></div>
            </div>
            <p class="text-xs text-slate-400">{{ number_format($diskUsed / 1073741824, 1) }} GB / {{ number_format($diskTotal / 1073741824, 1) }} GB</p>
        </div>

        {{-- SMTP Status --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">SMTP</span>
                @if($smtpStatus['global_configured'] && $smtpStatus['global_active'])
                    <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Active
                    </span>
                @elseif($smtpStatus['global_configured'])
                    <span class="inline-flex items-center gap-1 text-xs font-medium text-amber-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Inactive
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 text-xs font-medium text-red-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span> Not configured
                    </span>
                @endif
            </div>
            @if($smtpStatus['global_host'])
                <p class="text-sm font-semibold text-white truncate">{{ $smtpStatus['global_host'] }}</p>
                <p class="text-xs text-slate-400 truncate mt-0.5">{{ $smtpStatus['global_from'] }}</p>
            @else
                <p class="text-sm text-slate-500 italic">No global SMTP set</p>
            @endif
            @if($smtpStatus['org_custom_count'] > 0)
                <p class="text-xs text-slate-500 mt-2">+{{ $smtpStatus['org_custom_count'] }} org custom SMTP</p>
            @endif
            <a href="{{ route('admin.smtp.index') }}" class="mt-3 inline-block text-xs text-blue-400 hover:text-blue-300">Configure →</a>
        </div>

        {{-- Pending Jobs --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Pending Jobs</span>
                @if($pendingJobs > 0)
                    <form method="POST" action="{{ route('admin.system.clear-pending') }}"
                          onsubmit="confirmThenSubmit(event, 'Clear all {{ $pendingJobs }} pending jobs?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-amber-400 hover:text-amber-300 font-medium">Clear All</button>
                    </form>
                @endif
            </div>
            <p class="text-3xl font-bold text-white mb-2">{{ number_format($pendingJobs) }}</p>
            @if($pendingByType->count() > 0)
                <div class="space-y-1">
                    @foreach($pendingByType->take(4) as $type)
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-400 truncate max-w-[140px]" title="{{ $type->job_name }}">
                                {{ class_basename($type->job_name) }}
                            </span>
                            <span class="text-xs font-semibold text-slate-200 ml-2">{{ $type->count }}</span>
                        </div>
                    @endforeach
                    @if($pendingByType->count() > 4)
                        <p class="text-xs text-slate-500">+{{ $pendingByType->count() - 4 }} more types</p>
                    @endif
                </div>
            @else
                <p class="text-xs text-emerald-400">Queue is clear</p>
            @endif
        </div>

        {{-- Failed Jobs --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Failed Jobs</span>
                @if($failedJobs->count() > 0)
                    <div class="flex items-center gap-3">
                        <form method="POST" action="{{ route('admin.system.retry-all-failed') }}">
                            @csrf
                            <button type="submit" class="text-xs text-blue-400 hover:text-blue-300 font-medium">Retry All</button>
                        </form>
                        <form method="POST" action="{{ route('admin.system.delete-all-failed') }}"
                              onsubmit="confirmThenSubmit(event, 'Delete all {{ $failedJobs->count() }} failed jobs?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-400 hover:text-red-300 font-medium">Delete All</button>
                        </form>
                    </div>
                @endif
            </div>
            <p class="text-3xl font-bold {{ $failedJobs->count() > 0 ? 'text-red-400' : 'text-white' }} mb-2">
                {{ number_format($failedJobs->count()) }}
            </p>
            @if($failedJobs->count() > 0)
                @php
                    $failedByType = $failedJobs->groupBy('job_name')->map->count()->sortDesc()->take(4);
                @endphp
                <div class="space-y-1">
                    @foreach($failedByType as $jobName => $count)
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-400 truncate max-w-[140px]" title="{{ $jobName }}">
                                {{ class_basename($jobName) }}
                            </span>
                            <span class="text-xs font-semibold text-red-300 ml-2">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-xs text-emerald-400">No failed jobs</p>
            @endif
        </div>
    </div>

    {{-- ── SECTION 3: Failed Jobs Table ── --}}
    @if($failedJobs->count() > 0)
        <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-slate-700 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-slate-200">Failed Jobs</h3>
                    <p class="text-xs text-slate-400 mt-0.5">{{ $failedJobs->count() }} job(s) — click exception to see full trace</p>
                </div>
                <div class="flex items-center gap-3">
                    <form method="POST" action="{{ route('admin.system.retry-all-failed') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-400 border border-blue-500/30 rounded-lg hover:bg-blue-500/10 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Retry All
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.system.delete-all-failed') }}"
                          onsubmit="confirmThenSubmit(event, 'Delete all {{ $failedJobs->count() }} failed jobs? This cannot be undone.')">
                        @csrf @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-400 border border-red-500/30 rounded-lg hover:bg-red-500/10 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Delete All
                        </button>
                    </form>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead>
                        <tr class="bg-slate-700/50 text-slate-300 text-xs uppercase tracking-wider">
                            <th class="px-5 py-3">ID</th>
                            <th class="px-5 py-3">Job</th>
                            <th class="px-5 py-3">Queue</th>
                            <th class="px-5 py-3">Exception</th>
                            <th class="px-5 py-3">Failed At</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        @foreach($failedJobs as $job)
                            <tr class="hover:bg-slate-700/30 transition-colors group">
                                <td class="px-5 py-3 text-slate-400 font-mono text-xs">{{ $job->id }}</td>
                                <td class="px-5 py-3">
                                    <span class="text-white text-xs font-medium">{{ class_basename($job->job_name) }}</span>
                                    <p class="text-slate-500 text-xs mt-0.5 truncate max-w-[180px]" title="{{ $job->job_name }}">{{ $job->job_name }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-block px-2 py-0.5 bg-slate-700 rounded text-xs text-slate-300">{{ $job->queue }}</span>
                                </td>
                                <td class="px-5 py-3 max-w-xs">
                                    <button type="button"
                                            onclick="document.getElementById('trace-{{ $job->id }}').classList.toggle('hidden')"
                                            class="text-red-400 text-xs hover:text-red-300 text-left line-clamp-2 cursor-pointer">
                                        {{ \Illuminate\Support\Str::limit($job->exception, 120) }}
                                    </button>
                                    <pre id="trace-{{ $job->id }}" class="hidden mt-2 text-xs text-slate-300 bg-slate-900 rounded p-3 overflow-x-auto max-h-60 whitespace-pre-wrap break-all">{{ $job->exception }}</pre>
                                </td>
                                <td class="px-5 py-3 text-slate-400 text-xs whitespace-nowrap">{{ $job->failed_at }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <form method="POST" action="{{ route('admin.system.retry-job', $job->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-blue-400 hover:text-blue-300 text-xs font-medium">Retry</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.system.delete-job', $job->id) }}" class="inline ml-3"
                                          onsubmit="confirmThenSubmit(event, 'Delete job #{{ $job->id }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-400 hover:text-red-300 text-xs font-medium">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ── SECTION 4: Recent Errors + Export ── --}}
    <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-slate-200">Recent Errors</h3>
                <p class="text-xs text-slate-400 mt-0.5">Last {{ $recentErrors->count() }} ERROR/CRITICAL entries from application log</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.system.export-errors-text') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-300 border border-slate-600 rounded-lg hover:bg-slate-700 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export .txt
                </a>
                <a href="{{ route('admin.system.export-errors-json') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-teal-400 border border-teal-500/30 rounded-lg hover:bg-teal-500/10 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export .json
                </a>
            </div>
        </div>

        @if($recentErrors->count() > 0)
            <div class="max-h-96 overflow-y-auto divide-y divide-slate-700">
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
                        <p class="text-xs text-red-400 font-mono break-all mt-0.5">{{ \Illuminate\Support\Str::limit($message, 400) }}</p>
                    </div>
                @endforeach
            </div>
        @else
            <div class="px-6 py-10 text-center">
                <svg class="w-10 h-10 text-emerald-500 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-slate-300 font-medium text-sm">No Recent Errors</p>
                <p class="text-xs text-slate-400 mt-1">Application log is clean.</p>
            </div>
        @endif
    </div>

    {{-- Export for Claude Code hint --}}
    <div class="mt-4 flex items-center gap-2 text-xs text-slate-500">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Use <strong class="text-slate-400 mx-1">Export .txt</strong> or <strong class="text-slate-400 mx-1">Export .json</strong> to download a full error report and share directly with Claude Code for debugging.
    </div>

@endsection
