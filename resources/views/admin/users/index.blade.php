@extends('admin.layouts.app')

@section('title', 'Users')
@section('page-title', 'Users')

@section('breadcrumbs')
    <nav class="text-sm text-slate-400 mb-1">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-white">Dashboard</a>
        <span class="mx-1">/</span>
        <span class="text-slate-200">Users</span>
    </nav>
@endsection

@section('content')
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        {{-- Search --}}
        <form method="GET" action="{{ route('admin.users.index') }}" class="flex items-center gap-2">
            <input type="text"
                   name="search"
                   value="{{ request('search') }}"
                   placeholder="Search by name or email..."
                   class="bg-slate-700 border border-slate-600 text-slate-200 text-sm rounded-lg px-4 py-2 w-72 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <button type="submit"
                    class="px-4 py-2 bg-slate-600 hover:bg-slate-500 text-white text-sm font-medium rounded-lg transition-colors">
                Search
            </button>
            @if(request('search'))
                <a href="{{ route('admin.users.index') }}"
                   class="px-3 py-2 text-sm text-slate-400 hover:text-white transition-colors">
                    Clear
                </a>
            @endif
        </form>

        {{-- Export --}}
        <a href="{{ route('admin.users.export') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Export CSV
        </a>
    </div>

    <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden">
        <table class="w-full text-sm text-left">
            <thead>
                <tr class="bg-slate-700/50 text-slate-300 text-xs uppercase tracking-wider">
                    <th class="px-6 py-3">Name</th>
                    <th class="px-6 py-3">Email</th>
                    <th class="px-6 py-3">Organizations</th>
                    <th class="px-6 py-3">Registered</th>
                    <th class="px-6 py-3">Last Login</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700">
                @forelse($users as $user)
                    <tr class="hover:bg-slate-700/30 transition-colors">
                        <td class="px-6 py-4 text-white font-medium">{{ $user->name }}</td>
                        <td class="px-6 py-4 text-slate-300">{{ $user->email }}</td>
                        <td class="px-6 py-4 text-slate-300">
                            {{ $user->organizations->pluck('name')->implode(', ') ?: '—' }}
                        </td>
                        <td class="px-6 py-4 text-slate-400">{{ $user->created_at->format('Y-m-d') }}</td>
                        <td class="px-6 py-4 text-slate-400">{{ $user->last_login_at?->format('Y-m-d H:i') ?? 'Never' }}</td>
                        <td class="px-6 py-4">
                            @if($user->trashed())
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-900/30 text-red-400 border border-red-800">Suspended</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-900/30 text-green-400 border border-green-800">Active</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('admin.users.show', $user) }}"
                               class="text-sm text-blue-400 hover:text-blue-300 transition-colors">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                            @if(request('search'))
                                No users found matching "{{ request('search') }}".
                            @else
                                No users found.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
        <div class="mt-6">
            {{ $users->links() }}
        </div>
    @endif
@endsection
