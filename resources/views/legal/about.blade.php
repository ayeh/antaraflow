@extends('layouts.public')

@section('content')
<section class="text-center py-8">
    <h1 class="text-4xl font-bold text-gray-900">{{ $branding->appName() }}</h1>
    <p class="mt-4 text-lg text-gray-600">
        AI-powered meeting minutes, recording, and transcription for teams.
    </p>
    <div class="mt-8">
        <a href="{{ route('login') }}" class="inline-block rounded-lg px-6 py-3 text-white font-medium bg-primary hover:brightness-90 transition">
            Sign in to get started
        </a>
    </div>
</section>

<section class="mt-10 grid gap-6 sm:grid-cols-2">
    <div class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="font-semibold text-gray-900">Record &amp; transcribe</h2>
        <p class="mt-2 text-sm text-gray-600">Capture meetings live in your browser and get accurate, speaker-attributed transcripts automatically.</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="font-semibold text-gray-900">AI meeting minutes</h2>
        <p class="mt-2 text-sm text-gray-600">Turn transcripts into structured minutes, decisions, and action items with AI.</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="font-semibold text-gray-900">Calendar sync</h2>
        <p class="mt-2 text-sm text-gray-600">Connect Google or Microsoft calendars to sync meetings and get a reminder when a meeting is about to start.</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="font-semibold text-gray-900">Collaborate</h2>
        <p class="mt-2 text-sm text-gray-600">Share minutes, assign action items, and keep your team aligned across every meeting.</p>
    </div>
</section>

<section class="mt-10 text-center text-sm text-gray-500">
    <p>
        By using {{ $branding->appName() }} you agree to our
        <a href="{{ route('terms') }}" class="text-primary hover:underline">Terms of Service</a> and
        <a href="{{ route('privacy') }}" class="text-primary hover:underline">Privacy Policy</a>.
    </p>
</section>
@endsection
