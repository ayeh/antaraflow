@extends('layouts.app')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ route('projects.index') }}" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $project->name }}</h1>
                    @if($project->code)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300">{{ $project->code }}</span>
                    @endif
                    @if($project->is_active)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300">Active</span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400">Inactive</span>
                    @endif
                </div>
                @if($project->description)
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $project->description }}</p>
                @endif
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('projects.edit', $project) }}" class="bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">Edit</a>
            <form method="POST" action="{{ route('projects.destroy', $project) }}" onsubmit="return confirm('Are you sure you want to delete this project?')" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="bg-white dark:bg-slate-800 border border-red-300 dark:border-red-700 text-red-600 dark:text-red-400 px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">Delete</button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Content --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Members Section --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Members ({{ $project->members->count() }})</h2>

                @if($project->members->isNotEmpty())
                    <ul class="divide-y divide-gray-100 dark:divide-slate-700 mb-4">
                        @foreach($project->members as $member)
                            <li class="py-3 flex items-center justify-between">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-8 h-8 rounded-full bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center text-sm font-medium text-violet-700 dark:text-violet-300">
                                        {{ strtoupper(substr($member->name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">{{ $member->name }}</div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500">{{ $member->email }}</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 ml-4 flex-shrink-0">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">{{ ucfirst($member->pivot->role) }}</span>
                                    <form method="POST" action="{{ route('projects.members.remove', [$project, $member]) }}" onsubmit="return confirm('Remove this member?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">Remove</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-gray-400 dark:text-gray-500 mb-4">No members added yet.</p>
                @endif

                {{-- Add Member Form --}}
                <form method="POST" action="{{ route('projects.members.add', $project) }}" class="flex items-end gap-3 pt-4 border-t border-gray-100 dark:border-slate-700">
                    @csrf
                    <div class="flex-1">
                        <label for="user_id" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Add Member</label>
                        <select name="user_id" id="user_id" required
                            class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                            <option value="">Select a user...</option>
                            @foreach($orgMembers as $orgMember)
                                @unless($project->members->contains($orgMember->id))
                                    <option value="{{ $orgMember->id }}">{{ $orgMember->name }} ({{ $orgMember->email }})</option>
                                @endunless
                            @endforeach
                        </select>
                    </div>
                    <div class="w-32">
                        <label for="role" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Role</label>
                        <select name="role" id="role"
                            class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                            <option value="member">Member</option>
                            <option value="lead">Lead</option>
                            <option value="viewer">Viewer</option>
                        </select>
                    </div>
                    <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">Add</button>
                </form>
            </div>

            {{-- Recent Meetings Section --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Recent Meetings</h2>
                @if($project->meetings->isEmpty())
                    <p class="text-sm text-gray-400 dark:text-gray-500">No meetings linked to this project yet.</p>
                @else
                    <ul class="divide-y divide-gray-100 dark:divide-slate-700">
                        @foreach($project->meetings as $meeting)
                            <li class="py-3 flex items-center justify-between">
                                <div class="min-w-0">
                                    <a href="{{ route('meetings.show', $meeting) }}" class="text-sm font-medium text-gray-800 dark:text-gray-200 hover:text-violet-600 dark:hover:text-violet-400">{{ $meeting->title }}</a>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $meeting->meeting_date?->format('M j, Y') ?? 'No date' }}</p>
                                </div>
                                @php
                                    $statusColors = [
                                        'draft'       => 'bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400',
                                        'in_progress' => 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300',
                                        'finalized'   => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
                                        'approved'    => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
                                    ];
                                    $statusColor = $statusColors[$meeting->status->value] ?? 'bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400';
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColor }} ml-4 flex-shrink-0">
                                    {{ ucfirst(str_replace('_', ' ', $meeting->status->value)) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5">
                <h2 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Details</h2>
                <dl class="space-y-2">
                    @if($project->code)
                        <div class="flex justify-between text-sm">
                            <dt class="text-gray-500 dark:text-gray-400">Code</dt>
                            <dd class="font-medium text-gray-800 dark:text-gray-200 font-mono">{{ $project->code }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $project->is_active ? 'Active' : 'Inactive' }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Members</dt>
                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $project->members->count() }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Meetings</dt>
                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $project->meetings->count() }}</dd>
                    </div>
                    @if($project->createdBy)
                        <div class="flex justify-between text-sm">
                            <dt class="text-gray-500 dark:text-gray-400">Created By</dt>
                            <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $project->createdBy->name }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Created</dt>
                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $project->created_at->format('M j, Y') }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Updated</dt>
                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $project->updated_at->format('M j, Y') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
