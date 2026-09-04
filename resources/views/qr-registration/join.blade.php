<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Join a Meeting') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-50 dark:bg-slate-900 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">

        {{-- Brand header — honors the live/organization branding --}}
        <div class="flex items-center justify-center mb-5">
            @if($branding->logoUrl())
                <img src="{{ $branding->logoUrl() }}" alt="{{ $branding->appName() }}" class="h-7 w-auto" />
            @else
                <span class="text-lg font-bold" style="color: {{ $branding->get('primary_color', '#7c3aed') }};">{{ $branding->appName() }}</span>
            @endif
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-8">
            {{-- Header --}}
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center h-12 w-12 rounded-full mb-3" style="background-color:#E6F4F4;">
                    <svg class="h-6 w-6" style="color:#0D7377;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                </div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ __('Join a Meeting') }}</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ __('Enter the code shown on the meeting screen.') }}</p>
            </div>

            <form method="POST" action="{{ route('qr-registration.join.submit') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="join_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 text-center">
                        {{ __('Join code') }}
                    </label>
                    <input
                        type="text"
                        id="join_code"
                        name="join_code"
                        value="{{ old('join_code') }}"
                        required
                        maxlength="6"
                        autocomplete="off"
                        autocapitalize="characters"
                        autofocus
                        inputmode="text"
                        placeholder="ABC123"
                        oninput="this.value = this.value.toUpperCase()"
                        class="w-full px-3 py-3 text-center text-2xl font-mono font-bold tracking-[0.4em] uppercase border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent"
                        style="outline-color:#0D7377;"
                    />
                    @error('join_code') <p class="text-xs text-red-500 mt-2 text-center">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="w-full text-white py-2.5 px-4 rounded-lg text-sm font-medium transition-colors"
                        style="background-color:#0D7377;" onmouseover="this.style.backgroundColor='#095153'" onmouseout="this.style.backgroundColor='#0D7377'">
                    {{ __('Continue') }}
                </button>
            </form>

            <p class="mt-4 text-xs text-gray-400 dark:text-gray-500 text-center">
                {{ __('The code is shown on the meeting screen or shared by the organizer.') }}
            </p>
        </div>
        <x-antara-note-footer />
    </div>
</body>
</html>
