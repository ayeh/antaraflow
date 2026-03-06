@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Connected Accounts</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage your social login connections</p>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->has('social'))
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl px-4 py-3 text-sm text-red-700 dark:text-red-400">
            {{ $errors->first('social') }}
        </div>
    @endif

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Social Login Providers</h2>

        <div class="space-y-4">
            @php
                $providers = [
                    'google' => ['name' => 'Google', 'color' => 'red'],
                    'microsoft' => ['name' => 'Microsoft', 'color' => 'blue'],
                    'github' => ['name' => 'GitHub', 'color' => 'gray'],
                ];
                $linkedProviders = $socialAccounts->keyBy('provider');
            @endphp

            @foreach($providers as $providerKey => $provider)
                <div class="flex items-center justify-between p-4 rounded-lg border border-gray-200 dark:border-slate-600">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 flex items-center justify-center rounded-lg bg-gray-100 dark:bg-slate-700">
                            @if($providerKey === 'google')
                                <svg class="w-5 h-5" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                            @elseif($providerKey === 'microsoft')
                                <svg class="w-5 h-5" viewBox="0 0 24 24"><path d="M11.4 24H0V12.6h11.4V24z" fill="#00A4EF"/><path d="M24 24H12.6V12.6H24V24z" fill="#FFB900"/><path d="M11.4 11.4H0V0h11.4v11.4z" fill="#F25022"/><path d="M24 11.4H12.6V0H24v11.4z" fill="#7FBA00"/></svg>
                            @else
                                <svg class="w-5 h-5 text-gray-900 dark:text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/></svg>
                            @endif
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $provider['name'] }}</p>
                            @if($linkedProviders->has($providerKey))
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $linkedProviders[$providerKey]->provider_email ?? 'Connected' }}</p>
                            @else
                                <p class="text-xs text-gray-500 dark:text-gray-400">Not connected</p>
                            @endif
                        </div>
                    </div>

                    <div>
                        @if($linkedProviders->has($providerKey))
                            <form method="POST" action="{{ route('social.unlink', $providerKey) }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 font-medium">
                                    Unlink
                                </button>
                            </form>
                        @else
                            <a href="{{ route('social.redirect', $providerKey) }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-medium">
                                Connect
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
