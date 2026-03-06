@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Webhooks</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage outgoing webhook endpoints for event notifications</p>
        </div>
        @can('create', \App\Domain\Webhook\Models\WebhookEndpoint::class)
            <a href="{{ route('webhooks.create') }}" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">Add Endpoint</a>
        @endcan
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    @if($endpoints->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-12 text-center">
            <svg class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
            <p class="text-gray-500 dark:text-gray-400">No webhook endpoints configured yet.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($endpoints as $endpoint)
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $endpoint->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-400' }}">
                                    {{ $endpoint->is_active ? 'Active' : 'Inactive' }}
                                </span>
                                @if($endpoint->failure_count > 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                                        {{ $endpoint->failure_count }} failures
                                    </span>
                                @endif
                            </div>
                            <p class="text-sm font-mono text-gray-700 dark:text-gray-300 truncate">{{ $endpoint->url }}</p>
                            @if($endpoint->description)
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $endpoint->description }}</p>
                            @endif
                            <div class="flex flex-wrap gap-1 mt-2">
                                @foreach($endpoint->events as $event)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">{{ $event }}</span>
                                @endforeach
                            </div>
                        </div>
                        <div class="flex items-center gap-2 ml-4">
                            <a href="{{ route('webhooks.show', $webhook = $endpoint) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Deliveries</a>
                            @can('update', $endpoint)
                                <a href="{{ route('webhooks.edit', $webhook = $endpoint) }}" class="text-sm text-violet-600 dark:text-violet-400 hover:text-violet-700">Edit</a>
                            @endcan
                            @can('delete', $endpoint)
                                <form method="POST" action="{{ route('webhooks.destroy', $webhook = $endpoint) }}" class="inline" onsubmit="return confirm('Delete this webhook endpoint?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm text-red-600 dark:text-red-400 hover:text-red-700">Delete</button>
                                </form>
                            @endcan
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
