@extends('admin.layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    {{-- Stat Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
        {{-- Total Users --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-blue-600/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                    </svg>
                </div>
                <span class="text-sm text-slate-400">Total Users</span>
            </div>
            <p class="text-2xl font-bold text-white">{{ number_format($stats['total_users']) }}</p>
        </div>

        {{-- Total Organizations --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-violet-600/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <span class="text-sm text-slate-400">Total Organizations</span>
            </div>
            <p class="text-2xl font-bold text-white">{{ number_format($stats['total_organizations']) }}</p>
        </div>

        {{-- Total Meetings --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-600/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <span class="text-sm text-slate-400">Total Meetings</span>
            </div>
            <p class="text-2xl font-bold text-white">{{ number_format($stats['total_meetings']) }}</p>
        </div>

        {{-- Active Subscriptions --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-amber-600/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </div>
                <span class="text-sm text-slate-400">Active Subscriptions</span>
            </div>
            <p class="text-2xl font-bold text-white">{{ number_format($stats['active_subscriptions']) }}</p>
        </div>

        {{-- MRR --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-rose-600/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <span class="text-sm text-slate-400">MRR</span>
            </div>
            <p class="text-2xl font-bold text-white">RM {{ number_format($stats['mrr'], 2) }}</p>
        </div>
    </div>

    {{-- Period Toggle --}}
    <div class="flex items-center gap-2 mb-6">
        <span class="text-sm text-slate-400 mr-2">Period:</span>
        @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $value => $label)
            <a href="{{ route('admin.dashboard', ['period' => $value]) }}"
               class="px-4 py-1.5 text-sm font-medium rounded-lg transition-colors {{ $period === $value ? 'bg-violet-600 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Growth Charts Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {{-- User Growth Chart --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-sm font-semibold text-slate-200 mb-4">User Growth</h3>
            <canvas id="userGrowthChart" class="w-full" style="height: 256px;"></canvas>
        </div>

        {{-- Organization Growth Chart --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-sm font-semibold text-slate-200 mb-4">Organization Growth</h3>
            <canvas id="orgGrowthChart" class="w-full" style="height: 256px;"></canvas>
        </div>
    </div>

    {{-- Subscription Distribution + Activity Heatmap --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {{-- Subscription Distribution --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-sm font-semibold text-slate-200 mb-4">Subscription Distribution</h3>
            @if(count($subscriptionDistribution) > 0)
                <canvas id="subscriptionChart" class="w-full" style="height: 256px;"></canvas>
            @else
                <p class="text-slate-400 text-sm py-8 text-center">No active subscriptions.</p>
            @endif
        </div>

        {{-- Activity Heatmap --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-sm font-semibold text-slate-200 mb-4">Meeting Activity by Day (Last 90 Days)</h3>
            @php
                $maxActivity = max(array_values($activityHeatmap)) ?: 1;
            @endphp
            <div class="grid grid-cols-7 gap-2 mt-4">
                @foreach($activityHeatmap as $day => $count)
                    @php
                        $intensity = $maxActivity > 0 ? $count / $maxActivity : 0;
                        $bgClass = match(true) {
                            $intensity === 0.0 => 'bg-slate-700',
                            $intensity <= 0.25 => 'bg-violet-900/40',
                            $intensity <= 0.5 => 'bg-violet-800/60',
                            $intensity <= 0.75 => 'bg-violet-700/80',
                            default => 'bg-violet-600',
                        };
                    @endphp
                    <div class="flex flex-col items-center gap-2">
                        <div class="w-full aspect-square rounded-lg {{ $bgClass }} flex items-center justify-center">
                            <span class="text-xs font-medium text-white">{{ $count }}</span>
                        </div>
                        <span class="text-xs text-slate-400">{{ substr($day, 0, 3) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Tables Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Recent Registrations --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-700">
                <h3 class="text-sm font-semibold text-slate-200">Recent Registrations</h3>
            </div>
            <table class="w-full text-sm text-left">
                <thead>
                    <tr class="bg-slate-700/50 text-slate-300 text-xs uppercase tracking-wider">
                        <th class="px-6 py-3">Name</th>
                        <th class="px-6 py-3">Email</th>
                        <th class="px-6 py-3">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700">
                    @forelse($recentRegistrations as $user)
                        <tr class="hover:bg-slate-700/30 transition-colors">
                            <td class="px-6 py-3 text-white font-medium">{{ $user->name }}</td>
                            <td class="px-6 py-3 text-slate-300">{{ $user->email }}</td>
                            <td class="px-6 py-3 text-slate-400">{{ $user->created_at->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-8 text-center text-slate-400">No users yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Top Organizations --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-700">
                <h3 class="text-sm font-semibold text-slate-200">Top Organizations</h3>
            </div>
            <table class="w-full text-sm text-left">
                <thead>
                    <tr class="bg-slate-700/50 text-slate-300 text-xs uppercase tracking-wider">
                        <th class="px-6 py-3">Name</th>
                        <th class="px-6 py-3 text-right">Members</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700">
                    @forelse($topOrganizations as $org)
                        <tr class="hover:bg-slate-700/30 transition-colors">
                            <td class="px-6 py-3 text-white font-medium">{{ $org->name }}</td>
                            <td class="px-6 py-3 text-slate-300 text-right">{{ $org->members_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-6 py-8 text-center text-slate-400">No organizations yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Chart.js --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chartDefaults = {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#94a3b8' },
                        grid: { color: '#334155' },
                    },
                    x: {
                        ticks: { color: '#94a3b8' },
                        grid: { color: '#334155' },
                    },
                },
                plugins: {
                    legend: { labels: { color: '#e2e8f0' } },
                },
            };

            // User Growth Chart
            new Chart(document.getElementById('userGrowthChart'), {
                type: 'line',
                data: {
                    labels: @json(array_keys($userGrowth)),
                    datasets: [{
                        label: 'New Users',
                        data: @json(array_values($userGrowth)),
                        borderColor: '#7c3aed',
                        backgroundColor: 'rgba(124, 58, 237, 0.1)',
                        tension: 0.3,
                        fill: true,
                    }],
                },
                options: chartDefaults,
            });

            // Org Growth Chart
            new Chart(document.getElementById('orgGrowthChart'), {
                type: 'line',
                data: {
                    labels: @json(array_keys($orgGrowth)),
                    datasets: [{
                        label: 'New Organizations',
                        data: @json(array_values($orgGrowth)),
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.3,
                        fill: true,
                    }],
                },
                options: chartDefaults,
            });

            // Subscription Distribution Chart
            @if(count($subscriptionDistribution) > 0)
            new Chart(document.getElementById('subscriptionChart'), {
                type: 'doughnut',
                data: {
                    labels: @json(array_keys($subscriptionDistribution)),
                    datasets: [{
                        data: @json(array_values($subscriptionDistribution)),
                        backgroundColor: ['#7c3aed', '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#ec4899'],
                        borderColor: '#1e293b',
                        borderWidth: 2,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { color: '#e2e8f0', padding: 16 },
                        },
                    },
                },
            });
            @endif
        });
    </script>
@endsection
