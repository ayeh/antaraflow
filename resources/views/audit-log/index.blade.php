@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Audit Log</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Track all activity within your organization.</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('audit-log.index') }}" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Action</label>
                <select name="action" class="w-full text-sm border border-gray-300 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500">
                    <option value="">All Actions</option>
                    @foreach($actions as $action)
                        <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">User</label>
                <select name="user_id" class="w-full text-sm border border-gray-300 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500">
                    <option value="">All Users</option>
                    @foreach($orgUsers as $orgUser)
                        <option value="{{ $orgUser->id }}" {{ request('user_id') == $orgUser->id ? 'selected' : '' }}>{{ $orgUser->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full text-sm border border-gray-300 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full text-sm border border-gray-300 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500">
            </div>
        </div>
        <div class="mt-3 flex gap-2">
            <button type="submit" class="bg-violet-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-violet-700 transition-colors">Filter</button>
            <a href="{{ route('audit-log.index') }}" class="text-sm font-medium text-gray-600 dark:text-gray-300 px-4 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">Clear</a>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
        @if($logs->isEmpty())
            <div class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No audit log entries found.</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                    <thead class="bg-gray-50 dark:bg-slate-700/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Action</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Resource Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Resource ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">IP Address</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                        @foreach($logs as $log)
                            <tr x-data="{ expanded: false }" class="hover:bg-gray-50 dark:hover:bg-slate-700/30">
                                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $log->created_at->format('d M Y H:i') }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $log->user?->name ?? 'System' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">{{ $log->action }}</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                    @if($log->auditable_type)
                                        {{ class_basename($log->auditable_type) }}
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                    @if($log->auditable_id)
                                        #{{ $log->auditable_id }}
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-400 dark:text-gray-500">{{ $log->ip_address ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if($log->old_values || $log->new_values)
                                        <button
                                            @click="expanded = !expanded"
                                            class="text-xs font-medium text-violet-600 dark:text-violet-400 hover:text-violet-800 dark:hover:text-violet-200 transition-colors"
                                            x-text="expanded ? 'Hide' : 'Show'"
                                        ></button>
                                    @else
                                        <span class="text-xs text-gray-300 dark:text-gray-600">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                            @if($log->old_values || $log->new_values)
                                <tr x-show="expanded" x-cloak class="bg-gray-50 dark:bg-slate-700/20">
                                    <td colspan="7" class="px-4 pb-4 pt-0">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs mt-2">
                                            @if($log->old_values)
                                                <div>
                                                    <p class="font-medium text-gray-500 dark:text-gray-400 mb-1">Before</p>
                                                    <pre class="bg-gray-100 dark:bg-slate-800 rounded-lg p-3 overflow-x-auto text-gray-700 dark:text-gray-300">{{ json_encode($log->old_values, JSON_PRETTY_PRINT) }}</pre>
                                                </div>
                                            @endif
                                            @if($log->new_values)
                                                <div>
                                                    <p class="font-medium text-gray-500 dark:text-gray-400 mb-1">After</p>
                                                    <pre class="bg-gray-100 dark:bg-slate-800 rounded-lg p-3 overflow-x-auto text-gray-700 dark:text-gray-300">{{ json_encode($log->new_values, JSON_PRETTY_PRINT) }}</pre>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-100 dark:border-slate-700">
                {{ $logs->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
