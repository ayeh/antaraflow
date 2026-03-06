@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('organizations.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="flex items-center gap-3">
                @if($organization->logo_path)
                    <img src="{{ Storage::url($organization->logo_path) }}" alt="{{ $organization->name }}" class="w-10 h-10 rounded-xl object-cover border border-gray-200 dark:border-slate-600">
                @else
                    <div class="w-10 h-10 rounded-xl bg-violet-600 flex items-center justify-center text-white text-sm font-semibold">
                        {{ strtoupper(substr($organization->name, 0, 2)) }}
                    </div>
                @endif
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $organization->name }}</h1>
            </div>
        </div>
        <div class="flex items-center gap-3">
            @can('manageMembers', $organization)
            <a href="{{ route('organizations.members.index', $organization) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">Members</a>
            @endcan
            @can('manageSettings', $organization)
            <a href="{{ route('organizations.settings.edit', $organization) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">Settings</a>
            @endcan
            @can('update', $organization)
            <a href="{{ route('organizations.edit', $organization) }}" class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
                Edit
            </a>
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- About --}}
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">About</h2>
            <div class="border-t border-gray-200 dark:border-slate-700 mt-4 pt-4 space-y-4">
                <div>
                    <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</h3>
                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $organization->description ?: 'No description provided.' }}</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Slug</h3>
                        <p class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono">{{ $organization->slug }}</p>
                    </div>
                    <div>
                        <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Timezone</h3>
                        <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $organization->timezone ?? 'UTC' }}</p>
                    </div>
                    <div>
                        <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Language</h3>
                        <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                            @if($organization->language === 'ms') Bahasa Melayu
                            @else English
                            @endif
                        </p>
                    </div>
                </div>
                <div>
                    <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</h3>
                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $organization->created_at->format('M j, Y') }}</p>
                </div>
            </div>
        </div>

        {{-- Quick Stats --}}
        <div class="space-y-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Plan</h2>
                <div class="border-t border-gray-200 dark:border-slate-700 mt-4 pt-4">
                    @if($subscription)
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $subscription->subscriptionPlan->name ?? 'Current Plan' }}</p>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $subscription->status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-slate-700 dark:text-gray-300' }}">
                                {{ ucfirst($subscription->status) }}
                            </span>
                        </div>
                    @else
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">Free Plan</p>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-slate-700 dark:text-gray-300">Free</span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Members</h2>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $members->count() }}</span>
                </div>
                <div class="border-t border-gray-200 dark:border-slate-700 mt-4 pt-4">
                    @if($members->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">No members.</p>
                    @else
                        <ul class="space-y-3">
                            @foreach($members->take(5) as $member)
                                <li class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        @if($member->avatar_path)
                                            <img src="{{ Storage::url($member->avatar_path) }}" alt="{{ $member->name }}" class="w-8 h-8 rounded-full object-cover">
                                        @else
                                            <div class="w-8 h-8 rounded-full bg-violet-600 flex items-center justify-center text-white text-xs font-semibold">
                                                {{ strtoupper(substr($member->name, 0, 2)) }}
                                            </div>
                                        @endif
                                        <span class="text-sm text-gray-900 dark:text-white">{{ $member->name }}</span>
                                    </div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                        @if($member->pivot->role === 'owner') bg-violet-100 text-violet-800 dark:bg-violet-900/30 dark:text-violet-300
                                        @elseif($member->pivot->role === 'admin') bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300
                                        @else bg-gray-100 text-gray-700 dark:bg-slate-700 dark:text-gray-300
                                        @endif">
                                        {{ ucfirst($member->pivot->role) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                        @if($members->count() > 5)
                            <a href="{{ route('organizations.members.index', $organization) }}" class="block mt-3 text-sm text-violet-600 dark:text-violet-400 hover:text-violet-700 dark:hover:text-violet-300 font-medium transition-colors">
                                View all {{ $members->count() }} members
                            </a>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
