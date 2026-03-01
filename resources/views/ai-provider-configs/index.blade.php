@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">AI Provider Configurations</h1>
        <a href="{{ route('ai-provider-configs.create') }}" class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Provider
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if($configs->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 px-6 py-16 text-center">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <p class="text-sm text-gray-500 mb-4">No AI provider configurations yet.</p>
            <a href="{{ route('ai-provider-configs.create') }}" class="text-sm font-medium text-violet-600 hover:text-violet-700">Add your first provider</a>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($configs as $config)
                @php
                    $providerColors = [
                        'openai'    => 'bg-green-100 text-green-700',
                        'anthropic' => 'bg-orange-100 text-orange-700',
                        'google'    => 'bg-blue-100 text-blue-700',
                        'ollama'    => 'bg-gray-100 text-gray-700',
                    ];
                    $badgeClass = $providerColors[$config->provider] ?? 'bg-gray-100 text-gray-700';
                @endphp
                <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3 hover:border-gray-300 transition-colors">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-base font-bold text-gray-900">{{ $config->display_name }}</p>
                            <p class="text-sm text-gray-500 mt-0.5">{{ $config->model }}</p>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $badgeClass }} shrink-0">
                            {{ ucfirst($config->provider) }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        @if($config->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Inactive</span>
                        @endif

                        @if($config->is_default)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">Default</span>
                        @endif
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
                        <a href="{{ route('ai-provider-configs.edit', $config) }}" class="text-xs font-medium text-gray-500 hover:text-gray-700">Edit</a>
                        <form method="POST" action="{{ route('ai-provider-configs.destroy', $config) }}" onsubmit="return confirm('Delete this provider configuration?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs font-medium text-red-500 hover:text-red-700">Delete</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
