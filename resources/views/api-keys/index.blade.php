@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">API Keys</h1>
    </div>

    @if(session('api_key_created'))
        <div x-data="{ copied: false }" class="bg-amber-50 border border-amber-300 rounded-lg p-4 space-y-3">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-amber-800">Copy your API key now — it will not be shown again.</p>
                    <div class="mt-2 flex items-center gap-2">
                        <code class="flex-1 bg-white border border-amber-200 rounded px-3 py-2 text-sm font-mono text-gray-800 break-all">{{ session('api_key_created') }}</code>
                        <button
                            @click="navigator.clipboard.writeText(@js(session('api_key_created'))); copied = true; setTimeout(() => copied = false, 2000)"
                            class="shrink-0 inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-amber-100 text-amber-800 hover:bg-amber-200 transition-colors"
                        >
                            <svg x-show="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            <svg x-show="copied" class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if(session('success') && !session('api_key_created'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm space-y-1">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    {{-- Create API Key Form --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Create New API Key</h2>
        <form method="POST" action="{{ route('api-keys.store') }}" class="space-y-4">
            @csrf
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name') }}"
                    placeholder="e.g. Production Integration"
                    maxlength="100"
                    required
                    class="w-full sm:max-w-sm rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Permissions</label>
                <div class="flex flex-wrap gap-4">
                    @foreach(['read', 'write', 'delete'] as $permission)
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                name="permissions[]"
                                value="{{ $permission }}"
                                {{ in_array($permission, old('permissions', [])) ? 'checked' : '' }}
                                class="rounded border-gray-300 text-violet-600 focus:ring-violet-500"
                            >
                            <span class="text-sm text-gray-700 capitalize">{{ $permission }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label for="expires_at" class="block text-sm font-medium text-gray-700 mb-1">Expires At <span class="text-gray-400 font-normal">(optional)</span></label>
                <input
                    type="date"
                    id="expires_at"
                    name="expires_at"
                    value="{{ old('expires_at') }}"
                    min="{{ now()->addDay()->toDateString() }}"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                >
            </div>

            <div>
                <button type="submit" class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Generate API Key
                </button>
            </div>
        </form>
    </div>

    {{-- Existing API Keys Table --}}
    @if($apiKeys->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 px-6 py-16 text-center">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            <p class="text-sm text-gray-500">No API keys yet. Create one above.</p>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Key Prefix</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Permissions</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Used</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($apiKeys as $apiKey)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $apiKey->name }}</td>
                            <td class="px-6 py-4">
                                <code class="text-xs font-mono bg-gray-100 text-gray-700 px-2 py-1 rounded">af_{{ $apiKey->key }}...</code>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($apiKey->permissions as $permission)
                                        @php
                                            $permColors = ['read' => 'bg-blue-100 text-blue-700', 'write' => 'bg-amber-100 text-amber-700', 'delete' => 'bg-red-100 text-red-700'];
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $permColors[$permission] ?? 'bg-gray-100 text-gray-700' }}">
                                            {{ $permission }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ $apiKey->expires_at ? $apiKey->expires_at->format('M j, Y') : '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ $apiKey->last_used_at ? $apiKey->last_used_at->diffForHumans() : 'Never' }}
                            </td>
                            <td class="px-6 py-4">
                                @if($apiKey->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Inactive</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <form method="POST" action="{{ route('api-keys.destroy', $apiKey) }}" onsubmit="return confirm('Revoke this API key? This cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-medium text-red-500 hover:text-red-700 transition-colors">Revoke</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
