@extends('admin.layouts.app')

@section('title', 'Email Templates')
@section('page-title', 'Email Templates')

@section('breadcrumbs')
    <nav class="text-sm text-slate-400 mb-1">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-white">Dashboard</a>
        <span class="mx-1">/</span>
        <span class="text-slate-200">Email Templates</span>
    </nav>
@endsection

@section('content')
    <p class="text-slate-400 text-sm mb-6">Manage email templates used across the platform.</p>

    <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden">
        <table class="w-full text-sm text-left">
            <thead>
                <tr class="bg-slate-700/50 text-slate-300 text-xs uppercase tracking-wider">
                    <th class="px-6 py-3">Name</th>
                    <th class="px-6 py-3">Slug</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3">Variables</th>
                    <th class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700">
                @forelse($templates as $template)
                    <tr class="hover:bg-slate-700/30 transition-colors">
                        <td class="px-6 py-4 text-white font-medium">{{ $template->name }}</td>
                        <td class="px-6 py-4 text-slate-400 font-mono text-xs">{{ $template->slug }}</td>
                        <td class="px-6 py-4">
                            @if($template->is_active)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-900/30 text-green-400 border border-green-800">Active</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700 text-slate-400 border border-slate-600">Inactive</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-slate-300">{{ count($template->variables ?? []) }}</td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('admin.email-templates.edit', $template) }}"
                               class="text-sm text-blue-400 hover:text-blue-300 transition-colors">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                            No email templates found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
