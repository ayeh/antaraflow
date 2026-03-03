@extends('admin.layouts.app')

@section('title', 'Organizations')
@section('page-title', 'Organizations')

@section('breadcrumbs')
    <nav class="text-sm text-slate-400 mb-1">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-white">Dashboard</a>
        <span class="mx-1">/</span>
        <span class="text-slate-200">Organizations</span>
    </nav>
@endsection

@section('content')
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        {{-- Search & Filters --}}
        <form method="GET" action="{{ route('admin.organizations.index') }}" class="flex flex-wrap items-center gap-2">
            <input type="text"
                   name="search"
                   value="{{ request('search') }}"
                   placeholder="Search by name..."
                   class="bg-slate-700 border border-slate-600 text-slate-200 text-sm rounded-lg px-4 py-2 w-64 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">

            <select name="plan"
                    class="bg-slate-700 border border-slate-600 text-slate-200 text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">All Plans</option>
                @foreach($plans as $plan)
                    <option value="{{ $plan->id }}" @selected(request('plan') == $plan->id)>{{ $plan->name }}</option>
                @endforeach
            </select>

            <select name="status"
                    class="bg-slate-700 border border-slate-600 text-slate-200 text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">All Statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="suspended" @selected(request('status') === 'suspended')>Suspended</option>
            </select>

            <button type="submit"
                    class="px-4 py-2 bg-slate-600 hover:bg-slate-500 text-white text-sm font-medium rounded-lg transition-colors">
                Search
            </button>

            @if(request()->hasAny(['search', 'plan', 'status']))
                <a href="{{ route('admin.organizations.index') }}"
                   class="px-3 py-2 text-sm text-slate-400 hover:text-white transition-colors">
                    Clear
                </a>
            @endif
        </form>
    </div>

    <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden">
        <table class="w-full text-sm text-left">
            <thead>
                <tr class="bg-slate-700/50 text-slate-300 text-xs uppercase tracking-wider">
                    <th class="px-6 py-3">Name</th>
                    <th class="px-6 py-3">Owner</th>
                    <th class="px-6 py-3">Plan</th>
                    <th class="px-6 py-3">Members</th>
                    <th class="px-6 py-3">Created</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700">
                @forelse($organizations as $organization)
                    @php
                        $owner = $organization->members->firstWhere('pivot.role', 'owner');
                        $activeSubscription = $organization->subscriptions->where('status', 'active')->first();
                    @endphp
                    <tr class="hover:bg-slate-700/30 transition-colors">
                        <td class="px-6 py-4 text-white font-medium">{{ $organization->name }}</td>
                        <td class="px-6 py-4 text-slate-300">{{ $owner?->name ?? '—' }}</td>
                        <td class="px-6 py-4 text-slate-300">{{ $activeSubscription?->subscriptionPlan?->name ?? '—' }}</td>
                        <td class="px-6 py-4 text-slate-300">{{ $organization->members_count }}</td>
                        <td class="px-6 py-4 text-slate-400">{{ $organization->created_at->format('Y-m-d') }}</td>
                        <td class="px-6 py-4">
                            @if($organization->is_suspended)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-900/30 text-red-400 border border-red-800">Suspended</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-900/30 text-green-400 border border-green-800">Active</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('admin.organizations.show', $organization) }}"
                               class="text-sm text-blue-400 hover:text-blue-300 transition-colors">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                            @if(request()->hasAny(['search', 'plan', 'status']))
                                No organizations found matching your filters.
                            @else
                                No organizations found.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($organizations->hasPages())
        <div class="mt-6">
            {{ $organizations->links() }}
        </div>
    @endif
@endsection
