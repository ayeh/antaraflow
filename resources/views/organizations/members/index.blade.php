@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('organizations.show', $organization) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Members of :name', ['name' => $organization->name]) }}</h1>
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-300">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-300">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $ownerCount = $members->where('pivot.role', 'owner')->count();
    @endphp

    @can('manageMembers', $organization)
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Invite a member') }}</h2>
            <form method="POST" action="{{ route('organizations.members.store', $organization) }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                @csrf
                <div class="flex-1">
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Email address') }}</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="person@example.com"
                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                </div>
                <div class="sm:w-48">
                    <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Role') }}</label>
                    <select id="role" name="role"
                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                        @foreach ($assignableRoles as $role)
                            <option value="{{ $role->value }}" @selected(old('role', 'member') === $role->value)>{{ ucfirst($role->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors whitespace-nowrap">{{ __('Send invitation') }}</button>
            </form>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __("If the person doesn't have an account yet, they'll be able to create one when they accept.") }}</p>
        </div>
    @endcan

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-slate-700/50 border-b border-gray-200 dark:border-slate-700">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Name') }}</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Role') }}</th>
                        @can('manageMembers', $organization)
                            <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                    @forelse ($members as $member)
                        @php
                            $isLastOwner = $member->pivot->role === 'owner' && $ownerCount <= 1;
                            $canManage = ($manageable[$member->id] ?? false) && ! $isLastOwner;
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/30">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                {{ $member->name }}
                                <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">{{ $member->email }}</span>
                            </td>
                            <td class="px-6 py-4">
                                @if ($canManage)
                                    <form method="POST" action="{{ route('members.update', $member) }}">
                                        @csrf
                                        @method('PATCH')
                                        <select name="role" onchange="this.form.submit()"
                                            class="rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-3 py-1.5 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                                            @foreach ($assignableRoles as $role)
                                                <option value="{{ $role->value }}" @selected($member->pivot->role === $role->value)>{{ ucfirst($role->value) }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if($member->pivot->role === 'owner') bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300
                                        @elseif($member->pivot->role === 'admin') bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300
                                        @else bg-gray-100 text-gray-700 dark:bg-slate-600 dark:text-gray-300
                                        @endif">
                                        {{ ucfirst($member->pivot->role) }}
                                    </span>
                                @endif
                            </td>
                            @can('manageMembers', $organization)
                                <td class="px-6 py-4 text-right">
                                    @if ($canManage)
                                        <form method="POST" action="{{ route('members.destroy', $member) }}" onsubmit="confirmThenSubmit(event, @js(__('Remove :name from this organization?', ['name' => $member->name])), { title: @js(__('Remove member')), confirmLabel: @js(__('Remove')), type: 'danger' })">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 transition-colors">{{ __('Remove') }}</button>
                                        </form>
                                    @elseif ($isLastOwner)
                                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('Last owner') }}</span>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">&mdash;</span>
                                    @endif
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No members found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @can('manageMembers', $organization)
        @if ($invitations->isNotEmpty())
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Pending invitations') }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-slate-700/50 border-b border-gray-200 dark:border-slate-700">
                            <tr>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Email') }}</th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Role') }}</th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Expires') }}</th>
                                <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                            @foreach ($invitations as $invitation)
                                <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/30">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ $invitation->email }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ ucfirst($invitation->role->value) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $invitation->expires_at->format('M j, Y') }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-4">
                                            <form method="POST" action="{{ route('organizations.invitations.resend', [$organization, $invitation]) }}">
                                                @csrf
                                                <button type="submit" class="text-sm text-violet-600 hover:text-violet-700 dark:text-violet-400 dark:hover:text-violet-300 transition-colors">{{ __('Resend') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('organizations.invitations.destroy', [$organization, $invitation]) }}" onsubmit="confirmThenSubmit(event, @js(__('Revoke the invitation for :email?', ['email' => $invitation->email])), { title: @js(__('Revoke invitation')), confirmLabel: @js(__('Revoke')), type: 'warning' })">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 transition-colors">{{ __('Revoke') }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endcan
</div>
@endsection
