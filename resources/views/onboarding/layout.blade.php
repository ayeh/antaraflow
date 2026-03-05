<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Welcome to antaraFLOW</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100">
    <div class="min-h-full flex flex-col items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-md">
            {{-- Progress indicator --}}
            <div class="flex items-center justify-center gap-2 mb-8">
                @for ($i = 1; $i <= 3; $i++)
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium
                            {{ $i < $currentStep ? 'bg-primary-600 text-white' : ($i === $currentStep ? 'bg-primary-600 text-white ring-4 ring-primary-100 dark:ring-primary-900' : 'bg-slate-200 dark:bg-slate-700 text-slate-500') }}">
                            @if ($i < $currentStep)
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            @else
                                {{ $i }}
                            @endif
                        </div>
                        @if ($i < 3)
                            <div class="w-12 h-0.5 {{ $i < $currentStep ? 'bg-primary-600' : 'bg-slate-200 dark:bg-slate-700' }}"></div>
                        @endif
                    </div>
                @endfor
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg border border-slate-200 dark:border-slate-700 p-8">
                @yield('content')
            </div>

            <div class="mt-4 text-center">
                <form method="POST" action="{{ route('onboarding.skip') }}">
                    @csrf
                    <button type="submit" class="text-sm text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors">
                        Skip setup for now
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
