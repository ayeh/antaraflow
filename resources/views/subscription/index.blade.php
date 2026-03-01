@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Subscription Plans</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">View and manage your organization's subscription.</p>
    </div>

    {{-- Current Plan --}}
    @if($currentSubscription)
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-violet-200 dark:border-violet-800 p-5">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-3">Your Current Plan</h2>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $currentSubscription->subscriptionPlan?->name ?? 'Unknown Plan' }}</p>
                    <p class="text-sm mt-1">
                        Status:
                        <span class="font-semibold @if($currentSubscription->status === 'active') text-emerald-600 dark:text-emerald-400 @else text-amber-600 dark:text-amber-400 @endif">
                            {{ ucfirst($currentSubscription->status) }}
                        </span>
                    </p>
                    @if($currentSubscription->subscriptionPlan?->description)
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $currentSubscription->subscriptionPlan->description }}</p>
                    @endif
                </div>
                <div class="text-right text-sm text-gray-500 dark:text-gray-400 space-y-1">
                    @if($currentSubscription->starts_at)
                        <p>Started: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $currentSubscription->starts_at->format('d M Y') }}</span></p>
                    @endif
                    @if($currentSubscription->ends_at)
                        <p>Ends: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $currentSubscription->ends_at->format('d M Y') }}</span></p>
                    @endif
                    @if($currentSubscription->trial_ends_at && $currentSubscription->trial_ends_at->isFuture())
                        <p class="text-amber-600 dark:text-amber-400 font-medium">Trial ends: {{ $currentSubscription->trial_ends_at->format('d M Y') }}</p>
                    @endif
                    @if($currentSubscription->cancelled_at)
                        <p class="text-red-500 dark:text-red-400 font-medium">Cancelled: {{ $currentSubscription->cancelled_at->format('d M Y') }}</p>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-200 dark:border-amber-800 p-5 text-sm text-amber-800 dark:text-amber-300">
            You do not have an active subscription. Choose a plan below to get started.
        </div>
    @endif

    {{-- Plans Grid --}}
    @if($plans->isNotEmpty())
        <div>
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-4">Available Plans</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($plans as $plan)
                    @php
                        $isCurrent = $currentSubscription?->subscription_plan_id === $plan->id;
                    @endphp
                    <div class="relative bg-white dark:bg-slate-800 rounded-xl border {{ $isCurrent ? 'border-violet-400 dark:border-violet-600 ring-2 ring-violet-400 dark:ring-violet-600' : 'border-gray-200 dark:border-slate-700' }} p-5 flex flex-col">
                        @if($isCurrent)
                            <span class="absolute top-4 right-4 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">Current</span>
                        @endif

                        <div class="mb-4">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $plan->name }}</h3>
                            @if($plan->description)
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $plan->description }}</p>
                            @endif
                        </div>

                        <div class="mb-4">
                            <div class="flex items-baseline gap-1">
                                <span class="text-3xl font-extrabold text-gray-900 dark:text-white">${{ number_format((float) $plan->price_monthly, 0) }}</span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">/month</span>
                            </div>
                            @if($plan->price_yearly > 0)
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">${{ number_format((float) $plan->price_yearly, 0) }}/year</p>
                            @endif
                        </div>

                        {{-- Limits --}}
                        <ul class="mb-4 space-y-1.5 text-sm text-gray-600 dark:text-gray-300">
                            <li class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                Up to {{ $plan->max_users }} user{{ $plan->max_users === 1 ? '' : 's' }}
                            </li>
                            <li class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                {{ $plan->max_meetings_per_month }} meetings/month
                            </li>
                            <li class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                {{ $plan->max_audio_minutes_per_month }} audio minutes/month
                            </li>
                            <li class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                {{ number_format($plan->max_storage_mb) }} MB storage
                            </li>
                        </ul>

                        {{-- Features --}}
                        @if($plan->features && count($plan->features) > 0)
                            <ul class="mb-5 space-y-1.5 text-sm text-gray-600 dark:text-gray-300">
                                @foreach($plan->features as $feature)
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-violet-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        {{ $feature }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="mt-auto">
                            @if($isCurrent)
                                <button disabled class="w-full text-center text-sm font-medium px-4 py-2 rounded-lg bg-gray-100 dark:bg-slate-700 text-gray-400 dark:text-gray-500 cursor-not-allowed">
                                    Current Plan
                                </button>
                            @else
                                <a href="#" class="block w-full text-center text-sm font-medium px-4 py-2 rounded-lg bg-violet-600 text-white hover:bg-violet-700 transition-colors">
                                    Upgrade
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
            No subscription plans are currently available.
        </div>
    @endif

    <p class="text-sm text-center text-gray-400 dark:text-gray-500">
        To upgrade or change your plan, please <a href="mailto:support@example.com" class="text-violet-600 dark:text-violet-400 hover:underline">contact us</a>.
    </p>
</div>
@endsection
