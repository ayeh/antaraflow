@extends('admin.layouts.app')

@section('title', __('Per-Organization AI Budgets'))
@section('page-title', __('Per-Organization AI Budgets'))

@section('breadcrumbs')
    <nav class="text-sm text-slate-400 mb-1">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-white">{{ __('Dashboard') }}</a>
        <span class="mx-1">/</span>
        <a href="{{ route('admin.ai.index') }}" class="hover:text-white">{{ __('AI Control') }}</a>
        <span class="mx-1">/</span>
        <span class="text-slate-200">{{ __('Org Budgets') }}</span>
    </nav>
@endsection

@section('content')
    @php $inp = 'w-28 bg-slate-700 border border-slate-600 text-white rounded-lg px-2 py-1.5 text-sm focus:ring-blue-500 focus:border-blue-500'; @endphp

    <div class="max-w-5xl space-y-6">
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <p class="text-sm text-slate-400">
                {{ __('Set a daily and/or monthly USD limit per organization. When an org reaches a limit, its AI calls (MOM generation, transcription, prep briefs, search) are blocked with a clear message until the window resets or the limit is raised. Leave blank for no limit.') }}
            </p>
        </div>

        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-400 border-b border-slate-700">
                            <th class="py-2 pr-4 font-medium">{{ __('Organization') }}</th>
                            <th class="py-2 pr-4 font-medium text-right">{{ __('Today') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('Daily limit') }}</th>
                            <th class="py-2 pr-4 font-medium text-right">{{ __('This month') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('Monthly limit') }}</th>
                            <th class="py-2 font-medium text-right">{{ __('') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($organizations as $org)
                            @php
                                $b = $budgets[$org->id] ?? null;
                                $today = (float) ($todaySpend[$org->id] ?? 0);
                                $month = (float) ($monthSpend[$org->id] ?? 0);
                                $overDay = $b && $b->daily_limit && $today >= $b->daily_limit;
                                $overMonth = $b && $b->monthly_limit && $month >= $b->monthly_limit;
                            @endphp
                            <tr class="border-b border-slate-700/50 text-slate-200">
                                <td class="py-2 pr-4">
                                    {{ $org->name }}
                                    @if($overDay || $overMonth)
                                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-500/15 text-red-400">{{ __('OVER') }}</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-right {{ $overDay ? 'text-red-400' : '' }}">${{ number_format($today, 2) }}</td>
                                <td class="py-2 pr-4"><input form="ob{{ $org->id }}" name="daily_limit" type="number" step="0.01" min="0" value="{{ $b?->daily_limit }}" placeholder="—" class="{{ $inp }}"></td>
                                <td class="py-2 pr-4 text-right {{ $overMonth ? 'text-red-400' : '' }}">${{ number_format($month, 2) }}</td>
                                <td class="py-2 pr-4"><input form="ob{{ $org->id }}" name="monthly_limit" type="number" step="0.01" min="0" value="{{ $b?->monthly_limit }}" placeholder="—" class="{{ $inp }}"></td>
                                <td class="py-2 text-right"><button type="submit" form="ob{{ $org->id }}" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">{{ __('Save') }}</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-4 text-center text-slate-500">{{ __('No organizations.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @foreach($organizations as $org)
            <form id="ob{{ $org->id }}" method="POST" action="{{ route('admin.ai.org-budgets.update', $org) }}" class="hidden">@csrf @method('PUT')</form>
        @endforeach
    </div>
@endsection
