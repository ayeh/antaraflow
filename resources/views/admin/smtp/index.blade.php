@extends('admin.layouts.app')

@section('title', 'SMTP Configuration')
@section('page-title', 'SMTP Configuration')

@section('breadcrumbs')
    <nav class="text-sm text-slate-400 mb-1">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-white">Dashboard</a>
        <span class="mx-1">/</span>
        <span class="text-slate-200">SMTP Configuration</span>
    </nav>
@endsection

@section('content')
    {{-- Tab Navigation --}}
    <div class="flex items-center gap-1 mb-6 border-b border-slate-700">
        <a href="{{ route('admin.smtp.index') }}"
           class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors border-blue-500 text-blue-400">
            Global SMTP
        </a>
        <a href="{{ route('admin.smtp.org-index') }}"
           class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors border-transparent text-slate-400 hover:text-white">
            Per-Organization SMTP
        </a>
    </div>

    <div class="max-w-4xl space-y-8">
        {{-- Global SMTP Form --}}
        <form method="POST" action="{{ route('admin.smtp.update-global') }}" class="space-y-8">
            @csrf
            @method('PUT')

            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <h3 class="text-lg font-semibold text-white mb-4">SMTP Server Settings</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="host" class="block text-sm font-medium text-slate-300 mb-1">SMTP Host</label>
                        <input type="text" name="host" id="host"
                               value="{{ old('host', $config?->host) }}"
                               placeholder="smtp.example.com"
                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('host') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="port" class="block text-sm font-medium text-slate-300 mb-1">Port</label>
                        <input type="number" name="port" id="port"
                               value="{{ old('port', $config?->port ?? 587) }}"
                               min="1" max="65535"
                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('port') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="username" class="block text-sm font-medium text-slate-300 mb-1">Username</label>
                        <input type="text" name="username" id="username"
                               value="{{ old('username', $config ? $config->decrypted_username : '') }}"
                               placeholder="user@example.com"
                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('username') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-300 mb-1">Password</label>
                        <input type="password" name="password" id="password"
                               value="{{ old('password', $config ? $config->decrypted_password : '') }}"
                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('password') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="encryption" class="block text-sm font-medium text-slate-300 mb-1">Encryption</label>
                        <select name="encryption" id="encryption"
                                class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="tls" @selected(old('encryption', $config?->encryption) === 'tls')>TLS</option>
                            <option value="ssl" @selected(old('encryption', $config?->encryption) === 'ssl')>SSL</option>
                            <option value="none" @selected(old('encryption', $config?->encryption) === 'none')>None</option>
                        </select>
                        @error('encryption') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Sender Details</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="from_address" class="block text-sm font-medium text-slate-300 mb-1">From Address</label>
                        <input type="email" name="from_address" id="from_address"
                               value="{{ old('from_address', $config?->from_address) }}"
                               placeholder="noreply@example.com"
                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('from_address') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="from_name" class="block text-sm font-medium text-slate-300 mb-1">From Name</label>
                        <input type="text" name="from_name" id="from_name"
                               value="{{ old('from_name', $config?->from_name) }}"
                               placeholder="antaraFLOW"
                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('from_name') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Status</h3>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1"
                           @checked(old('is_active', $config?->is_active ?? true))
                           class="w-4 h-4 rounded border-slate-600 bg-slate-700 text-blue-500 focus:ring-blue-500 focus:ring-offset-0">
                    <span class="text-sm text-slate-300">Active</span>
                </label>
            </div>

            <div class="flex items-center gap-4">
                <button type="submit"
                        class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                    Save SMTP Configuration
                </button>
            </div>
        </form>

        {{-- Test Connection --}}
        @if($config)
            <form method="POST" action="{{ route('admin.smtp.test-global') }}" class="space-y-4">
                @csrf

                <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Test Connection</h3>
                    <div class="flex items-end gap-4">
                        <div class="flex-1">
                            <label for="test_email" class="block text-sm font-medium text-slate-300 mb-1">Send Test Email To</label>
                            <input type="email" name="test_email" id="test_email"
                                   value="{{ old('test_email') }}"
                                   placeholder="your@email.com"
                                   class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('test_email') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit"
                                class="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors whitespace-nowrap">
                            Send Test
                        </button>
                    </div>
                </div>
            </form>
        @endif
    </div>
@endsection
