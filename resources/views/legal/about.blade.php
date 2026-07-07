@extends('layouts.public')

@section('content')
<section class="text-center py-8">
    <h1 class="text-4xl font-bold text-gray-900">{{ $branding->appName() }}</h1>
    <p class="mt-4 text-lg text-gray-600">
        {{ __('AI-powered meeting minutes, recording, and transcription for teams.') }}
    </p>
    <div class="mt-8">
        <a href="{{ route('login') }}" class="inline-block rounded-lg px-6 py-3 text-white font-medium bg-primary hover:brightness-90 transition">
            {{ __('Sign in to get started') }}
        </a>
    </div>
</section>

<section class="mt-10 grid gap-6 sm:grid-cols-2">
    <div class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="font-semibold text-gray-900">{{ __('Record & transcribe') }}</h2>
        <p class="mt-2 text-sm text-gray-600">{{ __('Capture meetings live in your browser and get accurate, speaker-attributed transcripts automatically.') }}</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="font-semibold text-gray-900">{{ __('AI meeting minutes') }}</h2>
        <p class="mt-2 text-sm text-gray-600">{{ __('Turn transcripts into structured minutes, decisions, and action items with AI.') }}</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="font-semibold text-gray-900">{{ __('Calendar sync') }}</h2>
        <p class="mt-2 text-sm text-gray-600">{{ __('Connect Google or Microsoft calendars to sync meetings and get a reminder when a meeting is about to start.') }}</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="font-semibold text-gray-900">{{ __('Collaborate') }}</h2>
        <p class="mt-2 text-sm text-gray-600">{{ __('Share minutes, assign action items, and keep your team aligned across every meeting.') }}</p>
    </div>
</section>

<section class="mt-10 text-center text-sm text-gray-500">
    <p>
        {{ __('By using :app you agree to our', ['app' => $branding->appName()]) }}
        <a href="{{ route('terms') }}" class="text-primary hover:underline">{{ __('Terms of Service') }}</a> {{ __('and') }}
        <a href="{{ route('privacy') }}" class="text-primary hover:underline">{{ __('Privacy Policy') }}</a>.
    </p>
</section>
@endsection
