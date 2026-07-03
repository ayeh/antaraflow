@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Profile & Security</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage your personal information, preferences, and password</p>
    </div>

    @if(session('success'))
        <div class="flex items-center gap-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl px-4 py-3">
            <svg class="w-5 h-5 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <p class="text-sm text-green-700 dark:text-green-300">{{ session('success') }}</p>
        </div>
    @endif

    {{-- Profile Photo --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Profile Photo</h2>

        <div class="flex items-center gap-6">
            <div class="shrink-0">
                @if($user->avatar_path)
                    <img src="{{ Storage::url($user->avatar_path) }}" alt="{{ $user->name }}"
                        class="w-20 h-20 rounded-full object-cover border-2 border-gray-200 dark:border-slate-600">
                @else
                    <div class="w-20 h-20 rounded-full bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center border-2 border-gray-200 dark:border-slate-600">
                        <span class="text-2xl font-semibold text-violet-600 dark:text-violet-400">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                    </div>
                @endif
            </div>

            <form method="POST" action="{{ route('settings.profile.avatar') }}" enctype="multipart/form-data" class="flex-1">
                @csrf
                <div class="space-y-3">
                    <div>
                        <label for="avatar" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Upload new photo</label>
                        <input type="file" name="avatar" id="avatar" accept="image/*"
                            class="block w-full text-sm text-gray-500 dark:text-gray-400
                                   file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                                   file:text-sm file:font-medium file:bg-violet-50 file:text-violet-700
                                   dark:file:bg-violet-900/30 dark:file:text-violet-300
                                   hover:file:bg-violet-100 dark:hover:file:bg-violet-900/50 cursor-pointer">
                        @error('avatar')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">JPG, PNG or GIF — max 2MB</p>
                    </div>
                    <button type="submit"
                        class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
                        Upload Photo
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Personal Information + Regional Preferences --}}
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

    {{-- Change Password --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">Change Password</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Leave blank if you don't want to change your password</p>

        <form method="POST" action="{{ route('settings.profile.password') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Password <span class="text-red-500">*</span></label>
                <input type="password" name="current_password" id="current_password"
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                @error('current_password')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password" id="password"
                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                    @error('password')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm New Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password_confirmation" id="password_confirmation"
                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit"
                    class="bg-violet-600 text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
                    Update Password
                </button>
            </div>
        </form>
    </div>

    {{-- Two-Factor Authentication (placeholder) --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Two-Factor Authentication</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Add an extra layer of security to your account</p>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400">
                Coming Soon
            </span>
        </div>
    </div>
</div>
@endsection
