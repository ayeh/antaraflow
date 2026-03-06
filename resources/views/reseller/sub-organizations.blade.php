@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('reseller.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Sub-Organizations</h1>
        </div>
        @php $canCreate = !$resellerSetting->max_sub_organizations || $subOrganizations->count() < $resellerSetting->max_sub_organizations; @endphp
        @if($canCreate)
        <a href="{{ route('reseller.sub-organizations.create') }}" class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Create Sub-Organization
        </a>
        @endif
    </div>

    @if($resellerSetting->max_sub_organizations)
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg px-4 py-3">
        <p class="text-sm text-blue-700 dark:text-blue-300">
            {{ $subOrganizations->count() }} of {{ $resellerSetting->max_sub_organizations }} sub-organizations used.
        </p>
    </div>
    @endif

    {{-- Create Form --}}
    @if(!empty($showCreateForm))
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Create Sub-Organization</h2>
        <form action="{{ route('reseller.sub-organizations.store') }}" method="POST" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Organization Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:ring-violet-500 focus:border-violet-500 text-sm">
                    @error('name') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                    <input type="text" name="description" id="description" value="{{ old('description') }}" class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:ring-violet-500 focus:border-violet-500 text-sm">
                </div>
                <div>
                    <label for="owner_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Owner Name</label>
                    <input type="text" name="owner_name" id="owner_name" value="{{ old('owner_name') }}" required class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:ring-violet-500 focus:border-violet-500 text-sm">
                    @error('owner_name') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="owner_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Owner Email</label>
                    <input type="email" name="owner_email" id="owner_email" value="{{ old('owner_email') }}" required class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:ring-violet-500 focus:border-violet-500 text-sm">
                    @error('owner_email') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex justify-end gap-3">
                <a href="{{ route('reseller.sub-organizations') }}" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors">Cancel</a>
                <button type="submit" class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
                    Create Sub-Organization
                </button>
            </div>
        </form>
    </div>
    @endif

    {{-- Sub-Organizations List --}}
    @if($subOrganizations->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            <h3 class="mt-3 text-sm font-medium text-gray-900 dark:text-white">No sub-organizations</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating your first sub-organization.</p>
        </div>
    @else
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                <thead class="bg-gray-50 dark:bg-slate-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Organization</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Members</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Plan</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                    @foreach($subOrganizations as $subOrg)
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-violet-600 flex items-center justify-center text-white text-xs font-semibold">
                                    {{ strtoupper(substr($subOrg->name, 0, 2)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $subOrg->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $subOrg->slug }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $subOrg->members->count() }}</td>
                        <td class="px-6 py-4">
                            @php $sub = $subOrg->subscriptions->first(); @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-slate-700 dark:text-gray-300">
                                {{ $sub?->subscriptionPlan?->name ?? 'Free' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $subOrg->created_at->format('M j, Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
