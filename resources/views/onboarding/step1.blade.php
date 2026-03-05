@extends('onboarding.layout', ['currentStep' => 1])

@section('content')
    <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-2">Complete Your Profile</h2>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Let's start by setting up your profile.</p>

    <form method="POST" action="{{ route('onboarding.update', ['step' => 1]) }}">
        @csrf

        <div class="mb-4">
            <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Full Name</label>
            <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2 text-sm text-slate-900 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                required>
            @error('name')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full bg-primary-600 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-primary-700 transition-colors">
            Continue
        </button>
    </form>
@endsection
