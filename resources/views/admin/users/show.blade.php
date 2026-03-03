@extends('admin.layouts.app')

@section('title', $user->name)
@section('page-title', $user->name)

@section('breadcrumbs')
    <nav class="text-sm text-slate-400 mb-1">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-white">Dashboard</a>
        <span class="mx-1">/</span>
        <a href="{{ route('admin.users.index') }}" class="hover:text-white">Users</a>
        <span class="mx-1">/</span>
        <span class="text-slate-200">{{ $user->name }}</span>
    </nav>
@endsection

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        {{-- User Info Card --}}
        <div class="lg:col-span-2 bg-slate-800 border border-slate-700 rounded-xl p-6">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-white">{{ $user->name }}</h3>
                    <p class="text-slate-400 mt-1">{{ $user->email }}</p>
                </div>
                @if($user->trashed())
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-900/30 text-red-400 border border-red-800">Suspended</span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-900/30 text-green-400 border border-green-800">Active</span>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-4 mt-6">
                <div>
                    <span class="text-xs text-slate-500 uppercase tracking-wider">Registered</span>
                    <p class="text-slate-200 mt-1">{{ $user->created_at->format('M d, Y') }}</p>
                </div>
                <div>
                    <span class="text-xs text-slate-500 uppercase tracking-wider">Last Login</span>
                    <p class="text-slate-200 mt-1">{{ $user->last_login_at?->format('M d, Y H:i') ?? 'Never' }}</p>
                </div>
                <div>
                    <span class="text-xs text-slate-500 uppercase tracking-wider">Current Organization</span>
                    <p class="text-slate-200 mt-1">{{ $user->currentOrganization?->name ?? 'None' }}</p>
                </div>
                <div>
                    <span class="text-xs text-slate-500 uppercase tracking-wider">Timezone</span>
                    <p class="text-slate-200 mt-1">{{ $user->timezone ?? 'UTC' }}</p>
                </div>
            </div>
        </div>

        {{-- Stats Cards --}}
        <div class="space-y-4">
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <span class="text-xs text-slate-500 uppercase tracking-wider">Meetings Participated</span>
                <p class="text-3xl font-bold text-white mt-2">{{ number_format($meetingCount) }}</p>
            </div>
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <span class="text-xs text-slate-500 uppercase tracking-wider">Action Items Assigned</span>
                <p class="text-3xl font-bold text-white mt-2">{{ number_format($actionItemCount) }}</p>
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex items-center gap-3 mb-8">
        @if($user->trashed())
            <form method="POST" action="{{ route('admin.users.unsuspend', $user) }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Unsuspend User
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.users.suspend', $user) }}"
                  onsubmit="return confirm('Are you sure you want to suspend this user? They will not be able to access the platform.')">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    Suspend User
                </button>
            </form>

            <form method="POST" action="{{ route('admin.users.impersonate', $user) }}"
                  onsubmit="return confirm('You will be logged in as {{ $user->name }}. Continue?')">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Impersonate
                </button>
            </form>
        @endif
    </div>

    {{-- Organizations Table --}}
    <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700">
            <h3 class="text-sm font-semibold text-white uppercase tracking-wider">Organizations</h3>
        </div>
        <table class="w-full text-sm text-left">
            <thead>
                <tr class="bg-slate-700/50 text-slate-300 text-xs uppercase tracking-wider">
                    <th class="px-6 py-3">Organization</th>
                    <th class="px-6 py-3">Role</th>
                    <th class="px-6 py-3">Joined</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700">
                @forelse($user->organizations as $organization)
                    <tr class="hover:bg-slate-700/30 transition-colors">
                        <td class="px-6 py-4 text-white font-medium">{{ $organization->name }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-900/30 text-blue-400 border border-blue-800 capitalize">
                                {{ $organization->pivot->role }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-slate-400">{{ $organization->pivot->created_at?->format('M d, Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-8 text-center text-slate-400">
                            This user is not part of any organization.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
