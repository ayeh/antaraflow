@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('organizations.show', $organization) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Organization Settings</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage settings for {{ $organization->name }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    {{-- Section 1: General Settings --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">General Settings</h2>
        <div class="border-t border-gray-200 dark:border-slate-700 mt-4 pt-4">
            <form method="POST" action="{{ route('organizations.settings.update', $organization) }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Organization Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name', $organization->name) }}" required
                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                    <textarea name="description" id="description" rows="3"
                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none resize-none">{{ old('description', $organization->description) }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="timezone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Timezone <span class="text-red-500">*</span></label>
                        <select name="timezone" id="timezone"
                            class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                            @foreach([
                                'UTC' => 'UTC',
                                'America/New_York' => 'America/New_York',
                                'America/Chicago' => 'America/Chicago',
                                'America/Denver' => 'America/Denver',
                                'America/Los_Angeles' => 'America/Los_Angeles',
                                'Europe/London' => 'Europe/London',
                                'Europe/Paris' => 'Europe/Paris',
                                'Europe/Berlin' => 'Europe/Berlin',
                                'Asia/Kuala_Lumpur' => 'Asia/Kuala_Lumpur',
                                'Asia/Tokyo' => 'Asia/Tokyo',
                                'Asia/Jakarta' => 'Asia/Jakarta',
                                'Asia/Singapore' => 'Asia/Singapore',
                                'Australia/Sydney' => 'Australia/Sydney',
                            ] as $value => $label)
                                <option value="{{ $value }}" {{ old('timezone', $organization->timezone ?? 'UTC') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('timezone')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="language" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Language <span class="text-red-500">*</span></label>
                        <select name="language" id="language"
                            class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                            <option value="en" {{ old('language', $organization->language ?? 'en') === 'en' ? 'selected' : '' }}>English</option>
                            <option value="ms" {{ old('language', $organization->language ?? 'en') === 'ms' ? 'selected' : '' }}>Bahasa Melayu</option>
                        </select>
                        @error('language')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end pt-4 border-t border-gray-200 dark:border-slate-700">
                    <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">Save Settings</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Section 2: Organization Logo --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Organization Logo</h2>
        <div class="border-t border-gray-200 dark:border-slate-700 mt-4 pt-4">
            <form method="POST" action="{{ route('organizations.settings.logo', $organization) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div class="flex items-center gap-4">
                    @if($organization->logo_path)
                        <img src="{{ Storage::url($organization->logo_path) }}" alt="{{ $organization->name }}" class="w-16 h-16 rounded-xl object-cover border border-gray-200 dark:border-slate-600">
                    @else
                        <div class="w-16 h-16 rounded-xl bg-violet-600 flex items-center justify-center text-white text-lg font-semibold">
                            {{ strtoupper(substr($organization->name, 0, 2)) }}
                        </div>
                    @endif

                    <div class="flex-1">
                        <label for="logo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Upload new logo</label>
                        <input type="file" name="logo" id="logo" accept="image/*"
                            class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Max 2MB. Supported formats: JPG, PNG, GIF, WebP.</p>
                        @error('logo')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end pt-4 border-t border-gray-200 dark:border-slate-700">
                    <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">Upload Logo</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Section 3: Integrations --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Integrations</h2>
        <div class="border-t border-gray-200 dark:border-slate-700 mt-4 pt-4">
            <form method="POST" action="{{ route('organizations.settings.update', $organization) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="name" value="{{ $organization->name }}">
                <input type="hidden" name="timezone" value="{{ $organization->timezone ?? 'UTC' }}">
                <input type="hidden" name="language" value="{{ $organization->language ?? 'en' }}">

                <div>
                    <label for="teams_webhook_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Microsoft Teams Webhook URL</label>
                    <input type="url" name="teams_webhook_url" id="teams_webhook_url" value="{{ old('teams_webhook_url', $organization->teams_webhook_url) }}"
                        placeholder="https://outlook.office.com/webhook/..."
                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Add an Incoming Webhook URL from your Teams channel to receive meeting notifications.</p>
                    @error('teams_webhook_url')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end pt-4 border-t border-gray-200 dark:border-slate-700">
                    <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">Save Integrations</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Section: Cost Analytics --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Cost Analytics</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Configure hourly rates per role for meeting cost calculations.</p>
        <div class="border-t border-gray-200 dark:border-slate-700 mt-4 pt-4">
            @php $hourlyRates = $organization->settings['hourly_rates'] ?? []; @endphp
            <form method="POST" action="{{ route('organizations.settings.update', $organization) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="name" value="{{ $organization->name }}">
                <input type="hidden" name="timezone" value="{{ $organization->timezone ?? 'UTC' }}">
                <input type="hidden" name="language" value="{{ $organization->language ?? 'en' }}">

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="hourly_rate_admin" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Admin Rate ($/hr)</label>
                        <input type="number" name="settings[hourly_rates][admin]" id="hourly_rate_admin"
                            min="0" step="0.01"
                            value="{{ old('settings.hourly_rates.admin', $hourlyRates['admin'] ?? '') }}"
                            placeholder="e.g. 150"
                            class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                        @error('settings.hourly_rates.admin')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="hourly_rate_manager" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Manager Rate ($/hr)</label>
                        <input type="number" name="settings[hourly_rates][manager]" id="hourly_rate_manager"
                            min="0" step="0.01"
                            value="{{ old('settings.hourly_rates.manager', $hourlyRates['manager'] ?? '') }}"
                            placeholder="e.g. 100"
                            class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                        @error('settings.hourly_rates.manager')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="hourly_rate_member" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Member Rate ($/hr)</label>
                        <input type="number" name="settings[hourly_rates][member]" id="hourly_rate_member"
                            min="0" step="0.01"
                            value="{{ old('settings.hourly_rates.member', $hourlyRates['member'] ?? '') }}"
                            placeholder="e.g. 75"
                            class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                        @error('settings.hourly_rates.member')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end pt-4 border-t border-gray-200 dark:border-slate-700">
                    <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">Save Rates</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Section 4: Team Members --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Team Members</h2>
            <a href="{{ route('organizations.members.index', $organization) }}" class="text-sm text-violet-600 dark:text-violet-400 hover:text-violet-700 dark:hover:text-violet-300 font-medium transition-colors">Manage Members</a>
        </div>
        <div class="border-t border-gray-200 dark:border-slate-700 mt-4 pt-4">
            @if($members->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No members found.</p>
            @else
                <ul class="space-y-3">
                    @foreach($members as $member)
                        <li class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                @if($member->avatar_path)
                                    <img src="{{ Storage::url($member->avatar_path) }}" alt="{{ $member->name }}" class="w-8 h-8 rounded-full object-cover">
                                @else
                                    <div class="w-8 h-8 rounded-full bg-violet-600 flex items-center justify-center text-white text-xs font-semibold">
                                        {{ strtoupper(substr($member->name, 0, 2)) }}
                                    </div>
                                @endif
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $member->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $member->email }}</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @if($member->pivot->role === 'owner') bg-violet-100 text-violet-800 dark:bg-violet-900/30 dark:text-violet-300
                                @elseif($member->pivot->role === 'admin') bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300
                                @else bg-gray-100 text-gray-700 dark:bg-slate-700 dark:text-gray-300
                                @endif">
                                {{ ucfirst($member->pivot->role) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Section 4: Subscription --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Subscription</h2>
        <div class="border-t border-gray-200 dark:border-slate-700 mt-4 pt-4">
            @if($subscription)
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ $subscription->subscriptionPlan->name ?? 'Current Plan' }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Status: <span class="font-medium capitalize">{{ $subscription->status }}</span>
                            @if($subscription->ends_at)
                                &middot; Renews {{ $subscription->ends_at->format('M j, Y') }}
                            @endif
                        </p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        {{ $subscription->status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-slate-700 dark:text-gray-300' }}">
                        {{ ucfirst($subscription->status) }}
                    </span>
                </div>
            @else
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Free Plan</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">You are currently on the free plan.</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-slate-700 dark:text-gray-300">
                        Free
                    </span>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
