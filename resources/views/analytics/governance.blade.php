@extends('layouts.app')

@section('content')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<div class="space-y-6" x-data="governanceAnalytics()" x-init="fetchData()">

    {{-- Page Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Analytics</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Governance and compliance metrics</p>
        </div>
        <a :href="exportUrl" class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-violet-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            Export CSV
        </a>
    </div>

    {{-- Tab Navigation (pill style) --}}
    <div class="flex gap-1 p-1 bg-gray-100 dark:bg-slate-800 rounded-xl w-fit">
        <a href="{{ route('analytics.index') }}"
           class="px-4 py-2 rounded-xl text-sm font-medium transition-colors text-gray-600 dark:text-gray-400 hover:bg-white/60 dark:hover:bg-slate-700/60">
            Overview
        </a>
        <a href="{{ route('analytics.governance') }}"
           class="px-4 py-2 rounded-xl text-sm font-medium transition-colors bg-white dark:bg-slate-700 text-violet-600 dark:text-violet-400 shadow-sm">
            Governance
        </a>
        <a href="{{ route('analytics.intelligence') }}"
           class="px-4 py-2 rounded-xl text-sm font-medium transition-colors text-gray-600 dark:text-gray-400 hover:bg-white/60 dark:hover:bg-slate-700/60">
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
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Meeting Cost</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">$<span x-text="formatNumber(data.cost_estimate?.total_cost ?? 0)"></span></div>
                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5"><span x-text="data.cost_estimate?.meeting_count ?? 0"></span> meetings</div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Avg Attendance</div>
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1"><span x-text="avgAttendanceRate"></span>%</div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Compliance Score</div>
                    <div class="text-2xl font-bold mt-1" :class="complianceScoreColor"><span x-text="data.compliance_score?.overall_score ?? 0"></span>%</div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Avg Approval</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1"><span x-text="data.approval_turnaround?.avg_days ?? 0"></span> <span class="text-base font-normal text-gray-500 dark:text-gray-400">days</span></div>
                </div>
            </div>

            {{-- Charts section --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Attendance rate trends --}}
                <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Attendance Rate Trends</h2>
                    </div>
                    <div class="p-6">
                        <div class="relative h-64">
                            <canvas id="attendanceChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Meeting type distribution --}}
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Meeting Types</h2>
                    </div>
                    <div class="p-6">
                        <div class="relative h-64">
                            <canvas id="meetingTypeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Action item completion trends --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Action Item Completion Trends</h2>
                </div>
                <div class="p-6">
                    <div class="relative h-64">
                        <canvas id="actionItemChart"></canvas>
                    </div>
                </div>
            </div>

            {{-- Compliance breakdown --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Compliance Breakdown</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-gray-200 dark:divide-slate-700">
                    <div class="px-6 py-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Approved Meetings</div>
                        <div class="text-3xl font-bold text-green-600 dark:text-green-400 mt-1"><span x-text="data.compliance_score?.approved_percentage ?? 0"></span>%</div>
                    </div>
                    <div class="px-6 py-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Action Items Assigned</div>
                        <div class="text-3xl font-bold text-blue-600 dark:text-blue-400 mt-1"><span x-text="data.compliance_score?.action_items_assigned_percentage ?? 0"></span>%</div>
                    </div>
                    <div class="px-6 py-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">On-Time Completion</div>
                        <div class="text-3xl font-bold text-violet-600 dark:text-violet-400 mt-1"><span x-text="data.compliance_score?.on_time_completion_percentage ?? 0"></span>%</div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
function governanceAnalytics() {
    return {
        loading: true,
        data: {},
        startDate: new Date(new Date().setMonth(new Date().getMonth() - 6)).toISOString().split('T')[0],
        endDate: new Date().toISOString().split('T')[0],
        attendanceChartInstance: null,
        meetingTypeChartInstance: null,
        actionItemChartInstance: null,

        get exportUrl() {
            const params = new URLSearchParams({
                start_date: this.startDate,
                end_date: this.endDate,
            });
            return `{{ route('analytics.governance.export') }}?${params}`;
        },

        get avgAttendanceRate() {
            const trends = this.data.attendance_trends || [];
            if (trends.length === 0) return '0';
            const avg = trends.reduce((sum, t) => sum + t.rate, 0) / trends.length;
            return avg.toFixed(1);
        },

        get complianceScoreColor() {
            const score = this.data.compliance_score?.overall_score ?? 0;
            if (score >= 75) return 'text-green-600 dark:text-green-400';
            if (score >= 50) return 'text-yellow-600 dark:text-yellow-400';
            return 'text-red-600 dark:text-red-400';
        },

        formatNumber(num) {
            return Number(num).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        async fetchData() {
            this.loading = true;
            try {
                const params = new URLSearchParams({
                    start_date: this.startDate,
                    end_date: this.endDate,
                });
                const response = await fetch(`{{ route('analytics.governance.data') }}?${params}`);
                this.data = await response.json();
                this.$nextTick(() => this.renderCharts());
            } catch (error) {
                console.error('Failed to fetch governance data:', error);
            } finally {
                this.loading = false;
            }
        },

        renderCharts() {
            this.renderAttendanceChart();
            this.renderMeetingTypeChart();
            this.renderActionItemChart();
        },

        renderAttendanceChart() {
            if (this.attendanceChartInstance) this.attendanceChartInstance.destroy();
            const canvas = document.getElementById('attendanceChart');
            if (!canvas) return;

            const trends = this.data.attendance_trends || [];
            this.attendanceChartInstance = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: trends.map(t => t.month),
                    datasets: [{
                        label: 'Attendance Rate (%)',
                        data: trends.map(t => t.rate),
                        borderColor: '#7c3aed',
                        backgroundColor: 'rgba(124, 58, 237, 0.1)',
                        fill: true,
                        tension: 0.3,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } }
                }
            });
        },

        renderMeetingTypeChart() {
            if (this.meetingTypeChartInstance) this.meetingTypeChartInstance.destroy();
            const canvas = document.getElementById('meetingTypeChart');
            if (!canvas) return;

            const distribution = this.data.meeting_type_distribution || {};
            const labels = Object.keys(distribution).map(l => l.charAt(0).toUpperCase() + l.slice(1).replace(/_/g, ' '));
            const values = Object.values(distribution);

            this.meetingTypeChartInstance = new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: ['#7c3aed', '#3b82f6', '#22c55e', '#eab308', '#ef4444', '#6b7280'],
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

        renderActionItemChart() {
            if (this.actionItemChartInstance) this.actionItemChartInstance.destroy();
            const canvas = document.getElementById('actionItemChart');
            if (!canvas) return;

            const trends = this.data.action_item_trends || [];
            this.actionItemChartInstance = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: trends.map(t => t.month),
                    datasets: [
                        {
                            label: 'On Time',
                            data: trends.map(t => t.completed_on_time),
                            backgroundColor: '#22c55e',
                            borderRadius: 4,
                        },
                        {
                            label: 'Overdue',
                            data: trends.map(t => t.completed_overdue),
                            backgroundColor: '#ef4444',
                            borderRadius: 4,
                        },
                        {
                            label: 'Still Open',
                            data: trends.map(t => t.still_open),
                            backgroundColor: '#f59e0b',
                            borderRadius: 4,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
                    scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } } }
                }
            });
        }
    };
}
</script>
@endsection
