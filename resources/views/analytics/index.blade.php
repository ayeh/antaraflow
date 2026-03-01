@extends('layouts.app')

@section('content')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Analytics</h1>
            <p class="text-sm text-gray-500 mt-1">Last 6 months &mdash; {{ now()->subMonths(6)->format('M Y') }} to {{ now()->format('M Y') }}</p>
        </div>
    </div>

    {{-- Summary stat cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <div class="text-sm font-medium text-gray-500">Total Meetings</div>
            <div class="text-3xl font-bold text-gray-900 mt-1">{{ array_sum($meetingStats['meetings_per_month']) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <div class="text-sm font-medium text-gray-500">Completion Rate</div>
            <div class="text-3xl font-bold text-green-600 mt-1">{{ number_format($actionStats['completion_rate'], 1) }}%</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <div class="text-sm font-medium text-gray-500">Overdue Items</div>
            <div class="text-3xl font-bold text-red-600 mt-1">{{ $actionStats['overdue'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <div class="text-sm font-medium text-gray-500">Avg Meeting Duration</div>
            <div class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($meetingStats['avg_duration_minutes']) }} <span class="text-lg font-normal text-gray-500">min</span></div>
        </div>
    </div>

    {{-- Charts section --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Meetings per month --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Meetings per Month</h2>
            </div>
            <div class="p-6">
                <div class="relative h-64"
                    x-data="{}"
                    x-init="
                        new Chart(document.getElementById('meetingsChart'), {
                            type: 'bar',
                            data: {
                                labels: {{ json_encode(array_keys($meetingStats['meetings_per_month'])) }},
                                datasets: [{
                                    label: 'Meetings',
                                    data: {{ json_encode(array_values($meetingStats['meetings_per_month'])) }},
                                    backgroundColor: '#7c3aed',
                                    borderRadius: 4,
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } },
                                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                            }
                        });
                    ">
                    <canvas id="meetingsChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Status distribution --}}
        <div class="bg-white rounded-xl border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Status Distribution</h2>
            </div>
            <div class="p-6">
                <div class="relative h-64"
                    x-data="{}"
                    x-init="
                        new Chart(document.getElementById('statusChart'), {
                            type: 'doughnut',
                            data: {
                                labels: {{ json_encode(array_map('ucfirst', array_keys($meetingStats['status_distribution']))) }},
                                datasets: [{
                                    data: {{ json_encode(array_values($meetingStats['status_distribution'])) }},
                                    backgroundColor: ['#6b7280', '#3b82f6', '#eab308', '#22c55e'],
                                    borderWidth: 2,
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }
                            }
                        });
                    ">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Action items and participation --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Action items breakdown --}}
        <div class="bg-white rounded-xl border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Action Items Summary</h2>
            </div>
            <div class="p-6 space-y-4">
                <div class="relative h-48"
                    x-data="{}"
                    x-init="
                        new Chart(document.getElementById('actionsChart'), {
                            type: 'bar',
                            data: {
                                labels: ['Completed', 'Pending', 'Overdue'],
                                datasets: [{
                                    data: [{{ $actionStats['completed'] }}, {{ $actionStats['pending'] }}, {{ $actionStats['overdue'] }}],
                                    backgroundColor: ['#22c55e', '#f59e0b', '#ef4444'],
                                    borderRadius: 4,
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } },
                                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                            }
                        });
                    ">
                    <canvas id="actionsChart"></canvas>
                </div>
                <div class="grid grid-cols-4 gap-3 pt-2">
                    <div class="text-center">
                        <div class="text-xl font-bold text-gray-900">{{ $actionStats['total'] }}</div>
                        <div class="text-xs text-gray-500">Total</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xl font-bold text-green-600">{{ $actionStats['completed'] }}</div>
                        <div class="text-xs text-gray-500">Completed</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xl font-bold text-yellow-500">{{ $actionStats['pending'] }}</div>
                        <div class="text-xs text-gray-500">Pending</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xl font-bold text-red-600">{{ $actionStats['overdue'] }}</div>
                        <div class="text-xs text-gray-500">Overdue</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Top attendees --}}
        <div class="bg-white rounded-xl border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Top Attendees</h2>
                    <span class="text-sm text-gray-500">{{ $participationStats['total_attendees'] }} unique</span>
                </div>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($participationStats['top_attendees'] as $attendee)
                    <div class="flex items-center justify-between px-6 py-3">
                        <div class="text-sm font-medium text-gray-900">{{ $attendee['name'] }}</div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                            {{ $attendee['count'] }} {{ Str::plural('meeting', $attendee['count']) }}
                        </span>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center text-sm text-gray-500">No attendance data available.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- AI usage --}}
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">AI Usage</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-200">
            <div class="px-6 py-6">
                <div class="text-sm font-medium text-gray-500">Meetings with AI Extraction</div>
                <div class="text-3xl font-bold text-purple-600 mt-1">{{ $aiStats['total_meetings_with_ai'] }}</div>
            </div>
            <div class="px-6 py-6">
                <div class="text-sm font-medium text-gray-500">AI-Generated Action Items</div>
                <div class="text-3xl font-bold text-purple-600 mt-1">{{ $aiStats['total_action_items'] }}</div>
            </div>
        </div>
    </div>
</div>
@endsection

