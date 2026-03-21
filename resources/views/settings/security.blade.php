@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Security Settings</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage your password and account security</p>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    {{-- Change Password --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Change Password</h2>

        <form method="POST" action="{{ route('settings.security.password') }}" class="space-y-4">
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
                @error('password_confirmation')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
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
