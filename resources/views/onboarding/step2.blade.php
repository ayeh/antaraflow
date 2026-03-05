@extends('onboarding.layout', ['currentStep' => 2])

@section('content')
    <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-2">Set Up Your Workspace</h2>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Configure your organization settings.</p>

    <form method="POST" action="{{ route('onboarding.update', ['step' => 2]) }}">
        @csrf

        <div class="mb-4">
            <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Organization Name</label>
            <input type="text" name="name" id="name" value="{{ old('name', $organization->name) }}"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2 text-sm text-slate-900 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                required>
            @error('name')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="timezone" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Timezone</label>
            <select name="timezone" id="timezone"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2 text-sm text-slate-900 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                required>
                <option value="Asia/Kuala_Lumpur" {{ old('timezone', $organization->timezone ?? '') === 'Asia/Kuala_Lumpur' ? 'selected' : '' }}>Asia/Kuala Lumpur (GMT+8)</option>
                <option value="Asia/Singapore" {{ old('timezone', $organization->timezone ?? '') === 'Asia/Singapore' ? 'selected' : '' }}>Asia/Singapore (GMT+8)</option>
                <option value="Asia/Jakarta" {{ old('timezone', $organization->timezone ?? '') === 'Asia/Jakarta' ? 'selected' : '' }}>Asia/Jakarta (GMT+7)</option>
                <option value="UTC" {{ old('timezone', $organization->timezone ?? '') === 'UTC' ? 'selected' : '' }}>UTC (GMT+0)</option>
                <option value="America/New_York" {{ old('timezone', $organization->timezone ?? '') === 'America/New_York' ? 'selected' : '' }}>America/New York (EST)</option>
                <option value="Europe/London" {{ old('timezone', $organization->timezone ?? '') === 'Europe/London' ? 'selected' : '' }}>Europe/London (GMT)</option>
            </select>
            @error('timezone')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-6">
            <label for="language" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Language</label>
            <select name="language" id="language"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2 text-sm text-slate-900 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                required>
                <option value="en" {{ old('language', $organization->language ?? 'en') === 'en' ? 'selected' : '' }}>English</option>
                <option value="ms" {{ old('language', $organization->language ?? '') === 'ms' ? 'selected' : '' }}>Bahasa Melayu</option>
            </select>
            @error('language')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full bg-primary-600 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-primary-700 transition-colors">
            Continue
        </button>
    </form>
@endsection
