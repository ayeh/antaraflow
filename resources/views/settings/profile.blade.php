@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Profile Settings</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Update your name, timezone and locale preferences</p>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('settings.profile.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Personal Information --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Personal Information</h2>

            <div class="space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required
                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                    <input type="email" id="email" value="{{ $user->email }}" disabled
                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700/50 dark:text-gray-400 px-4 py-2 text-sm bg-gray-50 text-gray-500 cursor-not-allowed">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Email cannot be changed here.</p>
                </div>
            </div>
        </div>

        {{-- Regional Preferences --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Regional Preferences</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="timezone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Timezone</label>
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
                            <option value="{{ $value }}" {{ old('timezone', $settings->timezone ?? 'UTC') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('timezone')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="locale" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Language</label>
                    <select name="locale" id="locale"
                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                        <option value="en" {{ old('locale', $settings->locale ?? 'en') === 'en' ? 'selected' : '' }}>English</option>
                        <option value="ms" {{ old('locale', $settings->locale ?? 'en') === 'ms' ? 'selected' : '' }}>Bahasa Melayu</option>
                    </select>
                    @error('locale')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                class="bg-violet-600 text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
                Save Changes
            </button>
        </div>
    </form>
</div>
@endsection
