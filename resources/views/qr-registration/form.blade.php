<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meeting Registration</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-8">
            {{-- Header --}}
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center h-12 w-12 rounded-full bg-violet-100 dark:bg-violet-900/30 mb-3">
                    <svg class="h-6 w-6 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                </div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">Meeting Registration</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $meeting->title }}</p>
                @if($meeting->meeting_date)
                    <div class="flex items-center justify-center gap-3 mt-2 text-xs text-gray-500 dark:text-gray-400">
                        <span class="inline-flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            {{ $meeting->meeting_date->format('d M Y') }}
                        </span>
                        @if($meeting->start_time)
                            <span class="inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ $meeting->start_time->format('g:i A') }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Welcome Message --}}
            @if($qrToken->welcome_message)
                <div class="mb-6 p-3 bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-800 rounded-lg">
                    <p class="text-sm text-violet-700 dark:text-violet-300">{{ $qrToken->welcome_message }}</p>
                </div>
            @endif

            @php $requiredFields = $qrToken->required_fields ?? ['name']; @endphp

            <form method="POST" action="{{ route('qr-registration.submit', $qrToken->token) }}" class="space-y-4">
                @csrf

                {{-- Name --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Name @if(in_array('name', $requiredFields))<span class="text-red-500">*</span>@endif
                    </label>
                    <input type="text" name="name" {{ in_array('name', $requiredFields) ? 'required' : '' }} value="{{ old('name') }}" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-violet-500 focus:border-transparent" placeholder="Your full name" />
                    @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Email --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Email @if(in_array('email', $requiredFields))<span class="text-red-500">*</span>@endif
                    </label>
                    <input type="email" name="email" {{ in_array('email', $requiredFields) ? 'required' : '' }} value="{{ old('email') }}" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-violet-500 focus:border-transparent" placeholder="your@email.com" />
                    @error('email') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Phone --}}
                @if(in_array('phone', $requiredFields) || true)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Phone @if(in_array('phone', $requiredFields))<span class="text-red-500">*</span>@endif
                        </label>
                        <input type="tel" name="phone" {{ in_array('phone', $requiredFields) ? 'required' : '' }} value="{{ old('phone') }}" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-violet-500 focus:border-transparent" placeholder="Your phone number" />
                        @error('phone') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                @endif

                {{-- Company --}}
                @if(in_array('company', $requiredFields) || true)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Company @if(in_array('company', $requiredFields))<span class="text-red-500">*</span>@endif
                        </label>
                        <input type="text" name="company" {{ in_array('company', $requiredFields) ? 'required' : '' }} value="{{ old('company') }}" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-violet-500 focus:border-transparent" placeholder="Your company" />
                        @error('company') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                @endif

                <button type="submit" class="w-full bg-violet-600 text-white py-2.5 px-4 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
                    Register Attendance
                </button>
            </form>

            <p class="mt-4 text-xs text-gray-400 dark:text-gray-500 text-center">
                Your information is used solely for meeting attendance purposes.
            </p>
        </div>
    </div>
</body>
</html>
