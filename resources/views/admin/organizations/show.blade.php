@extends('admin.layouts.app')

@section('title', $organization->name)
@section('page-title', $organization->name)

@section('breadcrumbs')
    <nav class="text-sm text-slate-400 mb-1">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-white">Dashboard</a>
        <span class="mx-1">/</span>
        <a href="{{ route('admin.organizations.index') }}" class="hover:text-white">Organizations</a>
        <span class="mx-1">/</span>
        <span class="text-slate-200">{{ $organization->name }}</span>
    </nav>
@endsection

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        {{-- Organization Info Card --}}
        <div class="lg:col-span-2 bg-slate-800 border border-slate-700 rounded-xl p-6">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-white">{{ $organization->name }}</h3>
                    <p class="text-slate-400 mt-1">{{ $organization->slug }}</p>
                </div>
                @if($organization->is_suspended)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-900/30 text-red-400 border border-red-800">Suspended</span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-900/30 text-green-400 border border-green-800">Active</span>
                @endif
            </div>

            @if($organization->is_suspended && $organization->suspended_reason)
                <div class="mt-4 bg-red-900/20 border border-red-800 rounded-lg px-4 py-3">
                    <span class="text-xs text-red-400 uppercase tracking-wider font-medium">Suspension Reason</span>
                    <p class="text-red-300 text-sm mt-1">{{ $organization->suspended_reason }}</p>
                    @if($organization->suspended_at)
                        <p class="text-red-400/60 text-xs mt-2">Suspended on {{ $organization->suspended_at->format('M d, Y H:i') }}</p>
                    @endif
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4 mt-6">
                <div>
                    <span class="text-xs text-slate-500 uppercase tracking-wider">Created</span>
                    <p class="text-slate-200 mt-1">{{ $organization->created_at->format('M d, Y') }}</p>
                </div>
                <div>
                    <span class="text-xs text-slate-500 uppercase tracking-wider">Timezone</span>
                    <p class="text-slate-200 mt-1">{{ $organization->timezone ?? 'UTC' }}</p>
                </div>
                <div>
                    <span class="text-xs text-slate-500 uppercase tracking-wider">Language</span>
                    <p class="text-slate-200 mt-1">{{ strtoupper($organization->language ?? 'en') }}</p>
                </div>
                <div>
                    <span class="text-xs text-slate-500 uppercase tracking-wider">SMTP Config</span>
                    <p class="text-slate-200 mt-1">
                        @if($hasSmtpConfig)
                            <span class="text-green-400">Configured</span>
                        @else
                            <span class="text-slate-500">Not configured</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        {{-- Subscription Card --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <span class="text-xs text-slate-500 uppercase tracking-wider">Current Plan</span>
            @if($activeSubscription)
                <p class="text-2xl font-bold text-white mt-2">{{ $activeSubscription->subscriptionPlan->name }}</p>
                <p class="text-slate-400 text-sm mt-1">${{ number_format((float) $activeSubscription->subscriptionPlan->price_monthly, 2) }}/mo</p>
            @else
                <p class="text-2xl font-bold text-slate-500 mt-2">No Plan</p>
                <p class="text-slate-500 text-sm mt-1">No active subscription</p>
            @endif
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <span class="text-xs text-slate-500 uppercase tracking-wider">Total Members</span>
            <p class="text-3xl font-bold text-white mt-2">{{ number_format($organization->members->count()) }}</p>
        </div>
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <span class="text-xs text-slate-500 uppercase tracking-wider">Total Meetings</span>
            <p class="text-3xl font-bold text-white mt-2">{{ number_format($totalMeetings) }}</p>
            <p class="text-slate-500 text-xs mt-1">{{ number_format($meetingsLast30Days) }} in last 30 days</p>
        </div>
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <span class="text-xs text-slate-500 uppercase tracking-wider">Storage Used</span>
            <p class="text-3xl font-bold text-white mt-2">{{ number_format($storageUsedMb) }} MB</p>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex flex-wrap items-start gap-4 mb-8">
        {{-- Suspend / Unsuspend --}}
        @if($organization->is_suspended)
            <form method="POST" action="{{ route('admin.organizations.unsuspend', $organization) }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Unsuspend Organization
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.organizations.suspend', $organization) }}" class="flex items-end gap-2"
                  onsubmit="return confirm('Are you sure you want to suspend this organization?')">
                @csrf
                <div>
                    <label for="reason" class="block text-xs text-slate-400 mb-1">Suspension Reason</label>
                    <input type="text"
                           name="reason"
                           id="reason"
                           required
                           placeholder="Enter reason..."
                           class="bg-slate-700 border border-slate-600 text-slate-200 text-sm rounded-lg px-3 py-2 w-64 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent">
                    @error('reason')
                        <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    Suspend
                </button>
            </form>
        @endif

        {{-- Change Plan --}}
        <form method="POST" action="{{ route('admin.organizations.change-plan', $organization) }}" class="flex items-end gap-2">
            @csrf
            @method('PUT')
            <div>
                <label for="plan_id" class="block text-xs text-slate-400 mb-1">Change Plan</label>
                <select name="plan_id"
                        id="plan_id"
                        required
                        class="bg-slate-700 border border-slate-600 text-slate-200 text-sm rounded-lg px-3 py-2 w-48 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Select plan...</option>
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}" @selected($activeSubscription?->subscription_plan_id === $plan->id)>
                            {{ $plan->name }} (${{ number_format((float) $plan->price_monthly, 2) }}/mo)
                        </option>
                    @endforeach
                </select>
                @error('plan_id')
                    <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                Update Plan
            </button>
        </form>
    </div>

    {{-- Members Table --}}
    <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700">
            <h3 class="text-sm font-semibold text-white uppercase tracking-wider">Members</h3>
        </div>
        <table class="w-full text-sm text-left">
            <thead>
                <tr class="bg-slate-700/50 text-slate-300 text-xs uppercase tracking-wider">
                    <th class="px-6 py-3">Name</th>
                    <th class="px-6 py-3">Email</th>
                    <th class="px-6 py-3">Role</th>
                    <th class="px-6 py-3">Joined</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700">
                @forelse($organization->members as $member)
                    <tr class="hover:bg-slate-700/30 transition-colors">
                        <td class="px-6 py-4 text-white font-medium">{{ $member->name }}</td>
                        <td class="px-6 py-4 text-slate-300">{{ $member->email }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-900/30 text-blue-400 border border-blue-800 capitalize">
                                {{ $member->pivot->role }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-slate-400">{{ $member->pivot->created_at?->format('M d, Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-slate-400">
                            No members in this organization.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
