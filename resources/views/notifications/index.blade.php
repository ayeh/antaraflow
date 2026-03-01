@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Notifications</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Stay up to date with activity in your organization.</p>
        </div>

        @if($notifications->isNotEmpty())
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 bg-violet-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-violet-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Mark All Read
                </button>
            </form>
        @endif
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl px-4 py-3 text-sm text-green-700 dark:text-green-300">
            {{ session('success') }}
        </div>
    @endif

    @if($notifications->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 px-6 py-16 text-center">
            <svg class="mx-auto w-12 h-12 text-gray-300 dark:text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No notifications yet</p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">You're all caught up!</p>
        </div>
    @else
        @php
            $today = now()->startOfDay();
            $weekStart = now()->startOfWeek();

            $grouped = $notifications->getCollection()->groupBy(function ($notification) use ($today, $weekStart) {
                if ($notification->created_at->gte($today)) {
                    return 'Today';
                } elseif ($notification->created_at->gte($weekStart)) {
                    return 'This Week';
                } else {
                    return 'Older';
                }
            });
        @endphp

        <div class="space-y-6">
            @foreach(['Today', 'This Week', 'Older'] as $group)
                @if($grouped->has($group))
                    <div>
                        <h2 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">{{ $group }}</h2>
                        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden divide-y divide-gray-100 dark:divide-slate-700">
                            @foreach($grouped->get($group) as $notification)
                                <div class="flex items-start gap-4 px-5 py-4 {{ is_null($notification->read_at) ? 'bg-indigo-50 dark:bg-indigo-900/10' : 'hover:bg-gray-50 dark:hover:bg-slate-700/30' }} transition-colors">
                                    {{-- Unread indicator --}}
                                    <div class="flex-shrink-0 mt-1">
                                        @if(is_null($notification->read_at))
                                            <span class="block w-2 h-2 rounded-full bg-indigo-500"></span>
                                        @else
                                            <span class="block w-2 h-2 rounded-full bg-gray-200 dark:bg-slate-600"></span>
                                        @endif
                                    </div>

                                    {{-- Content --}}
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm {{ is_null($notification->read_at) ? 'font-semibold text-gray-900 dark:text-white' : 'font-normal text-gray-700 dark:text-gray-300' }}">
                                            {{ $notification->data['message'] ?? 'Notification' }}
                                        </p>
                                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                            {{ $notification->created_at->diffForHumans() }}
                                        </p>
                                    </div>

                                    {{-- Mark read action --}}
                                    @if(is_null($notification->read_at))
                                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="flex-shrink-0">
                                            @csrf
                                            <button type="submit"
                                                    class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-200 transition-colors whitespace-nowrap">
                                                Mark read
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        <div class="px-1">
            {{ $notifications->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
