@extends('layouts.app')

@section('content')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<div class="space-y-6" x-data="intelligenceAnalytics()" x-init="fetchData()">

    {{-- Page Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Analytics</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Cross-meeting intelligence and insights</p>
        </div>
    </div>

    {{-- Tab Navigation (pill style) --}}
    <div class="flex gap-1 p-1 bg-gray-100 dark:bg-slate-800 rounded-xl w-fit">
        <a href="{{ route('analytics.index') }}"
           class="px-4 py-2 rounded-xl text-sm font-medium transition-colors text-gray-600 dark:text-gray-400 hover:bg-white/60 dark:hover:bg-slate-700/60">
            Overview
        </a>
        <a href="{{ route('analytics.governance') }}"
           class="px-4 py-2 rounded-xl text-sm font-medium transition-colors text-gray-600 dark:text-gray-400 hover:bg-white/60 dark:hover:bg-slate-700/60">
            Governance
        </a>
        <a href="{{ route('analytics.intelligence') }}"
           class="px-4 py-2 rounded-xl text-sm font-medium transition-colors bg-white dark:bg-slate-700 text-violet-600 dark:text-violet-400 shadow-sm">
            Intelligence
        </a>
    </div>

    {{-- Date range filter --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
        <div class="flex flex-wrap items-center gap-4">
            <div>
                <label for="start_date" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Start Date</label>
                <input type="date" id="start_date" x-model="startDate" @change="fetchData()"
                    class="block rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 dark:text-white text-sm px-3 py-1.5 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
            </div>
            <div>
                <label for="end_date" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">End Date</label>
                <input type="date" id="end_date" x-model="endDate" @change="fetchData()"
                    class="block rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 dark:text-white text-sm px-3 py-1.5 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
            </div>
        </div>
    </div>

    {{-- Loading state --}}
    <template x-if="loading">
        <div class="flex items-center justify-center py-12">
            <svg class="animate-spin h-8 w-8 text-violet-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        </div>
    </template>

    <template x-if="!loading">
        <div class="space-y-6">
            {{-- Summary stat cards --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Topics</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1" x-text="(data.topic_frequency || []).length"></div>
                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Unique topics discussed</div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Follow-Through Rate</div>
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1"><span x-text="data.decision_follow_through?.follow_through_rate ?? 0"></span>%</div>
                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5"><span x-text="data.decision_follow_through?.total_decisions ?? 0"></span> decisions tracked</div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Risks</div>
                    <div class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1" x-text="data.risk_summary?.total ?? 0"></div>
                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5"><span x-text="data.risk_summary?.by_severity?.high ?? 0"></span> high severity</div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Unresolved Patterns</div>
                    <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1" x-text="(data.unresolved_patterns || []).length"></div>
                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Topics in 3+ meetings without decisions</div>
                </div>
            </div>

            {{-- Charts section --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Topic Frequency (horizontal bar) --}}
                <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Topic Frequency</h2>
                    </div>
                    <div class="p-6">
                        <div class="relative h-64">
                            <canvas id="topicFrequencyChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Decision Follow-Through (donut) --}}
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Decision Follow-Through</h2>
                    </div>
                    <div class="p-6">
                        <div class="relative h-64">
                            <canvas id="decisionFollowThroughChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Assignee Performance Table --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Assignee Performance</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-slate-700">
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Assignee</th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total</th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Completed</th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Completion Rate</th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">On-Time Rate</th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Avg Days</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                            <template x-for="(perf, idx) in (data.assignee_performance || [])" :key="idx">
                                <tr>
                                    <td class="px-6 py-3 font-medium text-gray-900 dark:text-white" x-text="perf.assignee"></td>
                                    <td class="px-6 py-3 text-gray-600 dark:text-gray-300" x-text="perf.total"></td>
                                    <td class="px-6 py-3 text-gray-600 dark:text-gray-300" x-text="perf.completed"></td>
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1 h-2 bg-gray-200 dark:bg-slate-600 rounded-full max-w-[100px]">
                                                <div class="h-2 bg-violet-600 rounded-full" :style="{ width: perf.completion_rate + '%' }"></div>
                                            </div>
                                            <span class="text-gray-600 dark:text-gray-300" x-text="perf.completion_rate + '%'"></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1 h-2 bg-gray-200 dark:bg-slate-600 rounded-full max-w-[100px]">
                                                <div class="h-2 bg-green-500 rounded-full" :style="{ width: perf.on_time_rate + '%' }"></div>
                                            </div>
                                            <span class="text-gray-600 dark:text-gray-300" x-text="perf.on_time_rate + '%'"></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-gray-600 dark:text-gray-300" x-text="perf.avg_days_to_complete"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <template x-if="(data.assignee_performance || []).length === 0">
                        <div class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No assignee data available.</div>
                    </template>
                </div>
            </div>

            {{-- Risk Summary + Unresolved Patterns --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Risk Summary (bar chart by severity) --}}
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Risk Summary</h2>
                    </div>
                    <div class="p-6">
                        <div class="relative h-48">
                            <canvas id="riskSummaryChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Unresolved Patterns (list) --}}
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Unresolved Patterns</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Topics discussed in 3+ meetings without decisions</p>
                    </div>
                    <div class="divide-y divide-gray-200 dark:divide-slate-700">
                        <template x-for="(pattern, idx) in (data.unresolved_patterns || [])" :key="idx">
                            <div class="px-6 py-3 flex items-center justify-between">
                                <div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white" x-text="pattern.topic"></div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        Discussed in <span class="font-medium" x-text="pattern.occurrences"></span> meetings
                                    </div>
                                </div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300">
                                    <span x-text="pattern.occurrences"></span>x
                                </span>
                            </div>
                        </template>
                        <template x-if="(data.unresolved_patterns || []).length === 0">
                            <div class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No unresolved patterns detected.</div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
function intelligenceAnalytics() {
    return {
        loading: true,
        data: {},
        startDate: new Date(new Date().setMonth(new Date().getMonth() - 6)).toISOString().split('T')[0],
        endDate: new Date().toISOString().split('T')[0],
        topicChartInstance: null,
        decisionChartInstance: null,
        riskChartInstance: null,

        async fetchData() {
            this.loading = true;
            try {
                const params = new URLSearchParams({
                    start_date: this.startDate,
                    end_date: this.endDate,
                });
                const response = await fetch(`{{ route('analytics.intelligence.data') }}?${params}`);
                this.data = await response.json();
                this.$nextTick(() => this.renderCharts());
            } catch (error) {
                console.error('Failed to fetch intelligence data:', error);
            } finally {
                this.loading = false;
            }
        },

        renderCharts() {
            this.renderTopicFrequencyChart();
            this.renderDecisionFollowThroughChart();
            this.renderRiskSummaryChart();
        },

        renderTopicFrequencyChart() {
            if (this.topicChartInstance) this.topicChartInstance.destroy();
            const canvas = document.getElementById('topicFrequencyChart');
            if (!canvas) return;

            const topics = (this.data.topic_frequency || []).slice(0, 10);
            this.topicChartInstance = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: topics.map(t => t.topic.length > 30 ? t.topic.substring(0, 30) + '...' : t.topic),
                    datasets: [{
                        label: 'Occurrences',
                        data: topics.map(t => t.count),
                        backgroundColor: '#7c3aed',
                        borderRadius: 4,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
                }
            });
        },

        renderDecisionFollowThroughChart() {
            if (this.decisionChartInstance) this.decisionChartInstance.destroy();
            const canvas = document.getElementById('decisionFollowThroughChart');
            if (!canvas) return;

            const dt = this.data.decision_follow_through || {};
            this.decisionChartInstance = new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: ['With Follow-Up', 'Without Follow-Up'],
                    datasets: [{
                        data: [dt.with_action_items || 0, dt.without_action_items || 0],
                        backgroundColor: ['#22c55e', '#ef4444'],
                        borderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }
                }
            });
        },

        renderRiskSummaryChart() {
            if (this.riskChartInstance) this.riskChartInstance.destroy();
            const canvas = document.getElementById('riskSummaryChart');
            if (!canvas) return;

            const severity = this.data.risk_summary?.by_severity || {};
            this.riskChartInstance = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: ['High', 'Medium', 'Low'],
                    datasets: [{
                        label: 'Risks',
                        data: [severity.high || 0, severity.medium || 0, severity.low || 0],
                        backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6'],
                        borderRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                }
            });
        }
    };
}
</script>
@endsection
