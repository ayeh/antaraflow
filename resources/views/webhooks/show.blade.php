@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('webhooks.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Webhook Deliveries</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 font-mono mt-1">{{ $webhook->url }}</p>
            </div>
        </div>
        <form method="POST" action="{{ route('webhooks.ping', $webhook) }}" class="inline">
            @csrf
            <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">Send Test Ping</button>
        </form>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    {{-- Endpoint Info --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-gray-500 dark:text-gray-400">Status</p>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $webhook->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-400' }}">
                    {{ $webhook->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400">Failures</p>
                <p class="font-medium text-gray-900 dark:text-white">{{ $webhook->failure_count }}</p>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400">Events</p>
                <p class="font-medium text-gray-900 dark:text-white">{{ count($webhook->events) }}</p>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400">Created</p>
                <p class="font-medium text-gray-900 dark:text-white">{{ $webhook->created_at->diffForHumans() }}</p>
            </div>
        </div>
    </div>

    {{-- Delivery Log --}}
    @if($deliveries->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-12 text-center">
            <p class="text-gray-500 dark:text-gray-400">No deliveries yet. Send a test ping to get started.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($deliveries as $delivery)
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4" x-data="{ open: false }">
                    <div class="flex items-center justify-between cursor-pointer" @click="open = !open">
                        <div class="flex items-center gap-3">
                            @if($delivery->successful)
                                <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
                            @else
                                <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                            @endif
                            <span class="text-sm font-mono text-gray-700 dark:text-gray-300">{{ $delivery->event }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $delivery->response_status ? "HTTP {$delivery->response_status}" : 'Connection Error' }}
                            </span>
                            <span class="text-xs text-gray-400 dark:text-gray-500">Attempt {{ $delivery->attempt }}</span>
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $delivery->created_at->diffForHumans() }}</span>
                    </div>
                    <div x-show="open" x-cloak class="mt-3 pt-3 border-t border-gray-200 dark:border-slate-700">
                        <pre class="text-xs text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-slate-900 rounded-lg p-3 overflow-x-auto max-h-48">{{ json_encode($delivery->payload, JSON_PRETTY_PRINT) }}</pre>
                        @if($delivery->response_body)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Response: {{ Str::limit($delivery->response_body, 200) }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
