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

        {{-- antaraNote brand header --}}
        <div class="flex items-center justify-center gap-2 mb-5">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 68 50" width="24" height="18" aria-hidden="true">
                <rect x="0"  y="21" width="7" height="16" rx="3.5" fill="#0D7377"/>
                <rect x="11" y="10" width="7" height="36" rx="3.5" fill="#0D7377"/>
                <rect x="22" y="16" width="7" height="25" rx="3.5" fill="#0D7377"/>
                <rect x="33" y="4"  width="7" height="50" rx="3.5" fill="#0D7377"/>
                <rect x="44" y="13" width="7" height="31" rx="3.5" fill="#0D7377"/>
                <rect x="55" y="8"  width="7" height="43" rx="3.5" fill="#0D7377"/>
                <rect x="66" y="19" width="7" height="22" rx="3.5" fill="#0D7377"/>
            </svg>
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                <span style="font-weight:400;">antara</span><span style="font-weight:700;">Note</span>
            </span>
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
