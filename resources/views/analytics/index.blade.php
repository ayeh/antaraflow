@extends('layouts.app')

@section('content')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<div class="space-y-6">

    {{-- Page Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Analytics</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                Last 6 months &mdash; {{ now()->subMonths(6)->format('M Y') }} to {{ now()->format('M Y') }}
            </p>
        </div>
    </div>

    {{-- Tab Navigation (pill style) --}}
    <div class="flex gap-1 p-1 bg-gray-100 dark:bg-slate-800 rounded-xl w-fit">
        <a href="{{ route('analytics.index') }}"
           class="px-4 py-2 rounded-xl text-sm font-medium transition-colors bg-white dark:bg-slate-700 text-violet-600 dark:text-violet-400 shadow-sm">
            Overview
        </a>
        <a href="{{ route('analytics.governance') }}"
           class="px-4 py-2 rounded-xl text-sm font-medium transition-colors text-gray-600 dark:text-gray-400 hover:bg-white/60 dark:hover:bg-slate-700/60">
            Governance
        </a>
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5 flex items-center gap-4">
            <div class="bg-violet-100 dark:bg-violet-900/30 rounded-xl p-3 shrink-0">
                <svg class="w-5 h-5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ array_sum($meetingStats['meetings_per_month']) }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Total Meetings</div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5 flex items-center gap-4">
            <div class="bg-green-100 dark:bg-green-900/30 rounded-xl p-3 shrink-0">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($actionStats['completion_rate'], 1) }}%</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Completion Rate</div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5 flex items-center gap-4">
            <div class="bg-red-100 dark:bg-red-900/30 rounded-xl p-3 shrink-0">
                <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $actionStats['overdue'] }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Overdue Items</div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5 flex items-center gap-4">
            <div class="bg-orange-100 dark:bg-orange-900/30 rounded-xl p-3 shrink-0">
                <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($meetingStats['avg_duration_minutes']) }}<span class="text-sm font-normal text-gray-500 dark:text-gray-400 ml-1">min</span></div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Avg Duration</div>
            </div>
        </div>
    </div>

    {{-- Charts: Meetings + Status --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Meetings per Month</h2>
            </div>
            <div class="p-6">
                <div class="relative h-64" x-data="{}" x-init="
                    new Chart(document.getElementById('meetingsChart'), {
                        type: 'bar',
                        data: {
                            labels: {{ json_encode(array_keys($meetingStats['meetings_per_month'])) }},
                            datasets: [{ label: 'Meetings', data: {{ json_encode(array_values($meetingStats['meetings_per_month'])) }}, backgroundColor: '#7c3aed', borderRadius: 6 }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
                    });
                ">
                    <canvas id="meetingsChart"></canvas>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Status Distribution</h2>
            </div>
            <div class="p-6">
                <div class="relative h-64" x-data="{}" x-init="
                    new Chart(document.getElementById('statusChart'), {
                        type: 'doughnut',
                        data: {
                            labels: {{ json_encode(array_map('ucfirst', array_keys($meetingStats['status_distribution']))) }},
                            datasets: [{ data: {{ json_encode(array_values($meetingStats['status_distribution'])) }}, backgroundColor: ['#6b7280', '#3b82f6', '#eab308', '#22c55e'], borderWidth: 2 }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } }
                    });
                ">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Action Items + Top Attendees --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Action Items Summary</h2>
            </div>
            <div class="p-6 space-y-4">
                <div class="relative h-48" x-data="{}" x-init="
                    new Chart(document.getElementById('actionsChart'), {
                        type: 'bar',
                        data: {
                            labels: ['Completed', 'Pending', 'Overdue'],
                            datasets: [{ data: [{{ $actionStats['completed'] }}, {{ $actionStats['pending'] }}, {{ $actionStats['overdue'] }}], backgroundColor: ['#22c55e', '#f59e0b', '#ef4444'], borderRadius: 6 }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
                    });
                ">
                    <canvas id="actionsChart"></canvas>
                </div>
                <div class="grid grid-cols-4 gap-3 pt-2 border-t border-gray-100 dark:border-slate-700">
                    <div class="text-center">
                        <div class="text-xl font-bold text-gray-900 dark:text-white">{{ $actionStats['total'] }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Total</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xl font-bold text-green-600 dark:text-green-400">{{ $actionStats['completed'] }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Done</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xl font-bold text-yellow-500 dark:text-yellow-400">{{ $actionStats['pending'] }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Pending</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xl font-bold text-red-600 dark:text-red-400">{{ $actionStats['overdue'] }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Overdue</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Top Attendees</h2>
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $participationStats['total_attendees'] }} unique</span>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-slate-700">
                @forelse($participationStats['top_attendees'] as $attendee)
                    <div class="flex items-center justify-between px-6 py-3">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $attendee['name'] }}</div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300">
                            {{ $attendee['count'] }} {{ Str::plural('meeting', $attendee['count']) }}
                        </span>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No attendance data available.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- AI Usage --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">AI Usage</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-200 dark:divide-slate-700">
            <div class="px-6 py-6 flex items-center gap-4">
                <div class="bg-violet-100 dark:bg-violet-900/30 rounded-xl p-3 shrink-0">
                    <svg class="w-5 h-5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $aiStats['total_meetings_with_ai'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Meetings with AI Extraction</div>
                </div>
            </div>
            <div class="px-6 py-6 flex items-center gap-4">
                <div class="bg-violet-100 dark:bg-violet-900/30 rounded-xl p-3 shrink-0">
                    <svg class="w-5 h-5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $aiStats['total_action_items'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">AI-Generated Action Items</div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
