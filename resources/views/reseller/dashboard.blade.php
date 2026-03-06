@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Reseller Dashboard</h1>
        <a href="{{ route('reseller.sub-organizations') }}" class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
            Manage Sub-Organizations
        </a>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
            <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sub-Organizations</h3>
            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $usageSummary['total_sub_orgs'] }}</p>
            @if($resellerSetting->max_sub_organizations)
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">of {{ $resellerSetting->max_sub_organizations }} allowed</p>
            @endif
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
            <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Users</h3>
            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $usageSummary['total_users'] }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
            <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Meetings</h3>
            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $usageSummary['total_meetings'] }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
            <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Commission (This Month)</h3>
            <p class="mt-2 text-3xl font-bold text-green-600 dark:text-green-400">${{ number_format($commission, 2) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $resellerSetting->commission_rate }}% rate</p>
        </div>
    </div>

    {{-- Reseller Info --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Reseller Details</h2>
            <div class="border-t border-gray-200 dark:border-slate-700 mt-4 pt-4 space-y-4">
                <div>
                    <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Subdomain</h3>
                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono">
                        {{ $resellerSetting->subdomain ? $resellerSetting->subdomain . '.' . config('app.domain', 'antaraflow.test') : 'Not configured' }}
                    </p>
                </div>
                @if($resellerSetting->custom_domain)
                <div>
                    <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Custom Domain</h3>
                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono">{{ $resellerSetting->custom_domain }}</p>
                </div>
                @endif
                <div>
                    <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Commission Rate</h3>
                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $resellerSetting->commission_rate }}%</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Branding</h2>
            <div class="border-t border-gray-200 dark:border-slate-700 mt-4 pt-4 space-y-4">
                @if($resellerSetting->branding_overrides)
                    @foreach($resellerSetting->branding_overrides as $key => $value)
                        <div>
                            <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ str_replace('_', ' ', $key) }}</h3>
                            @if(str_starts_with($value, '#'))
                                <div class="mt-1 flex items-center gap-2">
                                    <span class="inline-block w-4 h-4 rounded border border-gray-200 dark:border-slate-600" style="background-color: {{ $value }}"></span>
                                    <span class="text-sm text-gray-900 dark:text-gray-100 font-mono">{{ $value }}</span>
                                </div>
                            @else
                                <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $value }}</p>
                            @endif
                        </div>
                    @endforeach
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">No branding overrides configured. Platform defaults will be used.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
