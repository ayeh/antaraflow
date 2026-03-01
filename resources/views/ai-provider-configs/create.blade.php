@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('ai-provider-configs.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-900">Add Provider</h1>
    </div>

    <form method="POST" action="{{ route('ai-provider-configs.store') }}" class="bg-white rounded-xl border border-gray-200 p-6 space-y-6">
        @csrf

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div>
            <label for="provider" class="block text-sm font-medium text-gray-700 mb-1">Provider <span class="text-red-500">*</span></label>
            <select name="provider" id="provider" required class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                <option value="">Select a provider</option>
                <option value="openai" {{ old('provider') === 'openai' ? 'selected' : '' }}>OpenAI</option>
                <option value="anthropic" {{ old('provider') === 'anthropic' ? 'selected' : '' }}>Anthropic</option>
                <option value="google" {{ old('provider') === 'google' ? 'selected' : '' }}>Google</option>
                <option value="ollama" {{ old('provider') === 'ollama' ? 'selected' : '' }}>Ollama</option>
            </select>
        </div>

        <div>
            <label for="display_name" class="block text-sm font-medium text-gray-700 mb-1">Display Name <span class="text-red-500">*</span></label>
            <input type="text" name="display_name" id="display_name" value="{{ old('display_name') }}" required class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
        </div>

        <div>
            <label for="api_key" class="block text-sm font-medium text-gray-700 mb-1">API Key <span class="text-red-500">*</span></label>
            <input type="password" name="api_key" id="api_key" required class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
        </div>

        <div>
            <label for="model" class="block text-sm font-medium text-gray-700 mb-1">Model <span class="text-red-500">*</span></label>
            <input type="text" name="model" id="model" value="{{ old('model') }}" required placeholder="e.g. gpt-4o" class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
        </div>

        <div>
            <label for="base_url" class="block text-sm font-medium text-gray-700 mb-1">Base URL</label>
            <input type="text" name="base_url" id="base_url" value="{{ old('base_url') }}" placeholder="https://api.example.com/v1" class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
            <p class="text-xs text-gray-500 mt-1">Required for Ollama. Leave blank for standard cloud providers.</p>
        </div>

        <div class="space-y-3">
            <div class="flex items-center gap-3">
                <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500">
                <label for="is_active" class="text-sm font-medium text-gray-700">Active</label>
            </div>
            <div class="flex items-center gap-3">
                <input type="checkbox" name="is_default" id="is_default" value="1" {{ old('is_default') ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500">
                <label for="is_default" class="text-sm font-medium text-gray-700">Set as default provider</label>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
            <a href="{{ route('ai-provider-configs.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900">Cancel</a>
            <button type="submit" class="bg-violet-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">Add Provider</button>
        </div>
    </form>
</div>
@endsection
