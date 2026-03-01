@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Profile Settings</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Update your personal information and preferences</p>
    </div>

    {{-- Section 1: Profile Information --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Profile Information</h2>
        <div class="border-t border-gray-200 dark:border-slate-700 mt-4 pt-4">
            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                        @error('email')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                    <input type="text" name="phone" id="phone" value="{{ old('phone', $user->phone) }}" class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                    @error('phone')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="timezone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Timezone</label>
                        <select name="timezone" id="timezone" class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                            @foreach([
                                'UTC' => 'UTC',
                                'America/New_York' => 'America/New_York',
                                'America/Chicago' => 'America/Chicago',
                                'America/Denver' => 'America/Denver',
                                'America/Los_Angeles' => 'America/Los_Angeles',
                                'Europe/London' => 'Europe/London',
                                'Europe/Paris' => 'Europe/Paris',
                                'Europe/Berlin' => 'Europe/Berlin',
                                'Asia/Tokyo' => 'Asia/Tokyo',
                                'Asia/Jakarta' => 'Asia/Jakarta',
                                'Asia/Singapore' => 'Asia/Singapore',
                                'Australia/Sydney' => 'Australia/Sydney',
                            ] as $value => $label)
                                <option value="{{ $value }}" {{ old('timezone', $user->timezone) === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('timezone')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="language" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Language</label>
                        <select name="language" id="language" class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                            <option value="en" {{ old('language', $user->language) === 'en' ? 'selected' : '' }}>English</option>
                            <option value="id" {{ old('language', $user->language) === 'id' ? 'selected' : '' }}>Indonesian</option>
                        </select>
                        @error('language')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end pt-4 border-t border-gray-200 dark:border-slate-700">
                    <button type="submit" name="section" value="profile" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Section 2: Avatar --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Avatar</h2>
        <div class="border-t border-gray-200 dark:border-slate-700 mt-4 pt-4">
            <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div class="flex items-center gap-4">
                    @if($user->avatar_path)
                        <img src="{{ Storage::url($user->avatar_path) }}" alt="{{ $user->name }}" class="w-16 h-16 rounded-full object-cover">
                    @else
                        <div class="w-16 h-16 rounded-full bg-violet-600 flex items-center justify-center text-white text-lg font-semibold">
                            {{ strtoupper(substr($user->name, 0, 2)) }}
                        </div>
                    @endif

                    <div class="flex-1">
                        <label for="avatar" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Upload new avatar</label>
                        <input type="file" name="avatar" id="avatar" accept="image/*" class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                        @error('avatar')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end pt-4 border-t border-gray-200 dark:border-slate-700">
                    <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">Upload Avatar</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Section 3: Preferences --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Preferences</h2>
        <div class="border-t border-gray-200 dark:border-slate-700 mt-4 pt-4">
            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="preferences_theme" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Theme</label>
                        <select name="preferences[theme]" id="preferences_theme" class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                            <option value="system" {{ ($user->preferences['theme'] ?? 'system') === 'system' ? 'selected' : '' }}>System</option>
                            <option value="light" {{ ($user->preferences['theme'] ?? 'system') === 'light' ? 'selected' : '' }}>Light</option>
                            <option value="dark" {{ ($user->preferences['theme'] ?? 'system') === 'dark' ? 'selected' : '' }}>Dark</option>
                        </select>
                        @error('preferences.theme')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="preferences_default_meeting_duration" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Default Meeting Duration (minutes)</label>
                        <input type="number" name="preferences[default_meeting_duration]" id="preferences_default_meeting_duration" value="{{ old('preferences.default_meeting_duration', $user->preferences['default_meeting_duration'] ?? 60) }}" min="5" max="480" step="5" class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                        @error('preferences.default_meeting_duration')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notifications</label>
                    <div class="space-y-2">
                        @foreach([
                            'meeting_invite' => 'Meeting Invites',
                            'action_item_assigned' => 'Action Item Assigned',
                            'meeting_finalized' => 'Meeting Finalized',
                        ] as $key => $label)
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="preferences[notifications][]" value="{{ $key }}" {{ in_array($key, $user->preferences['notifications'] ?? []) ? 'checked' : '' }} class="w-4 h-4 rounded border-gray-300 dark:border-slate-600 text-violet-600 focus:ring-violet-500">
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('preferences.notifications')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end pt-4 border-t border-gray-200 dark:border-slate-700">
                    <button type="submit" name="section" value="preferences" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">Save Preferences</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Section 4: Change Password --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Change Password</h2>
        <div class="border-t border-gray-200 dark:border-slate-700 mt-4 pt-4">
            <form method="POST" action="{{ route('profile.password') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Password</label>
                    <input type="password" name="current_password" id="current_password" class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                    @error('current_password')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Password</label>
                    <input type="password" name="password" id="password" class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                    @error('password')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm New Password</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                    @error('password_confirmation')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end pt-4 border-t border-gray-200 dark:border-slate-700">
                    <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
