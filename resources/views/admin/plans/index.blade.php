@extends('admin.layouts.app')

@section('title', 'Subscription Plans')
@section('page-title', 'Subscription Plans')

@section('breadcrumbs')
    <nav class="text-sm text-slate-400 mb-1">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-white">Dashboard</a>
        <span class="mx-1">/</span>
        <span class="text-slate-200">Subscription Plans</span>
    </nav>
@endsection

@section('content')
    <div class="flex justify-between items-center mb-6">
        <p class="text-slate-400 text-sm">Manage subscription plans and pricing.</p>
        <a href="{{ route('admin.plans.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Plan
        </a>
    </div>

    <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden">
        <table class="w-full text-sm text-left">
            <thead>
                <tr class="bg-slate-700/50 text-slate-300 text-xs uppercase tracking-wider">
                    <th class="px-6 py-3">Sort</th>
                    <th class="px-6 py-3">Name</th>
                    <th class="px-6 py-3">Slug</th>
                    <th class="px-6 py-3">Monthly Price</th>
                    <th class="px-6 py-3">Yearly Price</th>
                    <th class="px-6 py-3">Subscribers</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700">
                @forelse($plans as $plan)
                    <tr class="hover:bg-slate-700/30 transition-colors">
                        <td class="px-6 py-4 text-slate-300">{{ $plan->sort_order }}</td>
                        <td class="px-6 py-4 text-white font-medium">{{ $plan->name }}</td>
                        <td class="px-6 py-4 text-slate-400 font-mono text-xs">{{ $plan->slug }}</td>
                        <td class="px-6 py-4 text-slate-300">RM {{ number_format($plan->price_monthly, 2) }}</td>
                        <td class="px-6 py-4 text-slate-300">RM {{ number_format($plan->price_yearly, 2) }}</td>
                        <td class="px-6 py-4 text-slate-300">{{ $plan->subscriptions_count }}</td>
                        <td class="px-6 py-4">
                            @if($plan->is_active)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-900/30 text-green-400 border border-green-800">Active</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700 text-slate-400 border border-slate-600">Inactive</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.plans.edit', $plan) }}"
                                   class="text-sm text-blue-400 hover:text-blue-300 transition-colors">Edit</a>
                                <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" class="inline"
                                      onsubmit="return confirm('Are you sure you want to delete this plan?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm text-red-400 hover:text-red-300 transition-colors">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-slate-400">
                            No subscription plans found. Create your first plan to get started.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
