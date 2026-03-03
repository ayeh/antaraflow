@extends('admin.layouts.app')

@section('title', 'Per-Organization SMTP')
@section('page-title', 'Per-Organization SMTP')

@section('breadcrumbs')
    <nav class="text-sm text-slate-400 mb-1">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-white">Dashboard</a>
        <span class="mx-1">/</span>
        <a href="{{ route('admin.smtp.index') }}" class="hover:text-white">SMTP Configuration</a>
        <span class="mx-1">/</span>
        <span class="text-slate-200">Per-Organization</span>
    </nav>
@endsection

@section('content')
    {{-- Tab Navigation --}}
    <div class="flex items-center gap-1 mb-6 border-b border-slate-700">
        <a href="{{ route('admin.smtp.index') }}"
           class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors border-transparent text-slate-400 hover:text-white">
            Global SMTP
        </a>
        <a href="{{ route('admin.smtp.org-index') }}"
           class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors border-blue-500 text-blue-400">
            Per-Organization SMTP
        </a>
    </div>

    {{-- Organizations Table --}}
    <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden">
        <table class="w-full text-sm text-left">
            <thead>
                <tr class="bg-slate-700/50 text-slate-300 text-xs uppercase tracking-wider">
                    <th class="px-6 py-3">Organization</th>
                    <th class="px-6 py-3">SMTP Status</th>
                    <th class="px-6 py-3">Host</th>
                    <th class="px-6 py-3">From Address</th>
                    <th class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700">
                @forelse($organizations as $organization)
                    @php
                        $smtpConfig = $smtpConfigs->get($organization->id);
                    @endphp
                    <tr class="hover:bg-slate-700/30 transition-colors">
                        <td class="px-6 py-4 text-white font-medium">{{ $organization->name }}</td>
                        <td class="px-6 py-4">
                            @if($smtpConfig && $smtpConfig->is_active)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-900/30 text-green-400 border border-green-800">Configured</span>
                            @elseif($smtpConfig)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-900/30 text-yellow-400 border border-yellow-800">Inactive</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700/50 text-slate-400 border border-slate-600">Not Configured</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-slate-300">{{ $smtpConfig?->host ?? '—' }}</td>
                        <td class="px-6 py-4 text-slate-300">{{ $smtpConfig?->from_address ?? '—' }}</td>
                        <td class="px-6 py-4 text-right">
                            <button type="button"
                                    onclick="document.getElementById('org-form-{{ $organization->id }}').classList.toggle('hidden')"
                                    class="text-sm text-blue-400 hover:text-blue-300 transition-colors">
                                {{ $smtpConfig ? 'Edit' : 'Configure' }}
                            </button>
                            @if($smtpConfig)
                                <button type="button"
                                        onclick="document.getElementById('org-test-{{ $organization->id }}').classList.toggle('hidden')"
                                        class="ml-3 text-sm text-green-400 hover:text-green-300 transition-colors">
                                    Test
                                </button>
                            @endif
                        </td>
                    </tr>
                    {{-- Inline Edit Form --}}
                    <tr id="org-form-{{ $organization->id }}" class="hidden">
                        <td colspan="5" class="px-6 py-4 bg-slate-800/50">
                            <form method="POST" action="{{ route('admin.smtp.update-org', $organization) }}" class="space-y-4">
                                @csrf
                                @method('PUT')

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-300 mb-1">Host</label>
                                        <input type="text" name="host"
                                               value="{{ old('host', $smtpConfig?->host) }}"
                                               placeholder="smtp.example.com"
                                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-300 mb-1">Port</label>
                                        <input type="number" name="port"
                                               value="{{ old('port', $smtpConfig?->port ?? 587) }}"
                                               min="1" max="65535"
                                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-300 mb-1">Encryption</label>
                                        <select name="encryption"
                                                class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                                            <option value="tls" @selected(old('encryption', $smtpConfig?->encryption) === 'tls')>TLS</option>
                                            <option value="ssl" @selected(old('encryption', $smtpConfig?->encryption) === 'ssl')>SSL</option>
                                            <option value="none" @selected(old('encryption', $smtpConfig?->encryption) === 'none')>None</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-300 mb-1">Username</label>
                                        <input type="text" name="username"
                                               value="{{ old('username', $smtpConfig ? $smtpConfig->decrypted_username : '') }}"
                                               placeholder="user@example.com"
                                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-300 mb-1">Password</label>
                                        <input type="password" name="password"
                                               value="{{ old('password', $smtpConfig ? $smtpConfig->decrypted_password : '') }}"
                                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-300 mb-1">From Address</label>
                                        <input type="email" name="from_address"
                                               value="{{ old('from_address', $smtpConfig?->from_address) }}"
                                               placeholder="noreply@org.com"
                                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-300 mb-1">From Name</label>
                                        <input type="text" name="from_name"
                                               value="{{ old('from_name', $smtpConfig?->from_name) }}"
                                               placeholder="Organization Name"
                                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div class="flex items-end">
                                        <label class="flex items-center gap-3 cursor-pointer pb-2">
                                            <input type="hidden" name="is_active" value="0">
                                            <input type="checkbox" name="is_active" value="1"
                                                   @checked(old('is_active', $smtpConfig?->is_active ?? true))
                                                   class="w-4 h-4 rounded border-slate-600 bg-slate-700 text-blue-500 focus:ring-blue-500 focus:ring-offset-0">
                                            <span class="text-sm text-slate-300">Active</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <button type="submit"
                                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                                        Save
                                    </button>
                                    <button type="button"
                                            onclick="document.getElementById('org-form-{{ $organization->id }}').classList.add('hidden')"
                                            class="px-4 py-2 bg-slate-600 hover:bg-slate-500 text-white text-sm font-medium rounded-lg transition-colors">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    {{-- Inline Test Form --}}
                    @if($smtpConfig)
                        <tr id="org-test-{{ $organization->id }}" class="hidden">
                            <td colspan="5" class="px-6 py-4 bg-slate-800/50">
                                <form method="POST" action="{{ route('admin.smtp.test-org', $organization) }}" class="flex items-end gap-4">
                                    @csrf
                                    <div class="flex-1">
                                        <label class="block text-sm font-medium text-slate-300 mb-1">Send Test Email To</label>
                                        <input type="email" name="test_email"
                                               placeholder="your@email.com"
                                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <button type="submit"
                                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors whitespace-nowrap">
                                        Send Test
                                    </button>
                                    <button type="button"
                                            onclick="document.getElementById('org-test-{{ $organization->id }}').classList.add('hidden')"
                                            class="px-4 py-2 bg-slate-600 hover:bg-slate-500 text-white text-sm font-medium rounded-lg transition-colors">
                                        Cancel
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                            No organizations found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
