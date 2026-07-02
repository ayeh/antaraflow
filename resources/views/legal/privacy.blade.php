@extends('layouts.public')

@section('content')
@php($app = $branding->appName())
<article class="space-y-6">
    <header>
        <h1 class="text-3xl font-bold text-gray-900">Privacy Policy</h1>
        <p class="mt-2 text-sm text-gray-500">Last updated: 2 July 2026</p>
    </header>

    <p>This Privacy Policy explains how {{ $app }} ("we", "us") collects, uses, and protects your information when you use our meeting minutes, recording, and transcription service (the "Service").</p>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">Information we collect</h2>
        <ul class="list-disc list-inside space-y-1">
            <li><strong>Account information</strong> — when you sign in with Google or Microsoft, we receive your name, email address, and basic profile to create and secure your account.</li>
            <li><strong>Calendar data</strong> — if you connect a Google or Microsoft calendar, we access your calendar events to provide the features you enable (see below).</li>
            <li><strong>Meeting content</strong> — audio you record, transcripts, minutes, action items, and related content you create in the Service.</li>
            <li><strong>Usage data</strong> — basic technical logs needed to operate and secure the Service.</li>
        </ul>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">How we use Google user data</h2>
        <p>When you connect a Google account, we request access to your Google Calendar events (<code>calendar.events</code>) and the read-only list of your calendars (<code>calendar.calendarlist.readonly</code>), and use them solely to:</p>
        <ul class="list-disc list-inside space-y-1">
            <li>Read your upcoming events so we can notify you when a meeting is about to start (if you enable "Notify on start").</li>
            <li>Create, update, or remove calendar events that correspond to meetings you choose to sync from {{ $app }}.</li>
        </ul>
        <p>We access Google user data only to provide these user-facing features, at your direction. We do not use it for advertising, and we do not sell it.</p>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">Limited Use disclosure</h2>
        <p>{{ $app }}'s use and transfer to any other app of information received from Google APIs will adhere to the
            <a href="https://developers.google.com/terms/api-services-user-data-policy" class="text-primary hover:underline" target="_blank" rel="noopener">Google API Services User Data Policy</a>,
            including the Limited Use requirements.</p>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">How we share information</h2>
        <p>We do not sell your personal information. We share it only with sub-processors that help us operate the Service (for example, our AI transcription and email delivery providers), and only to the extent necessary to provide the Service, or where required by law.</p>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">Data storage &amp; retention</h2>
        <p>OAuth tokens are stored encrypted at rest. We retain your content for as long as your account is active. You can disconnect a calendar at any time from your account settings, which revokes and deletes the stored tokens for that connection.</p>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">Deleting your data</h2>
        <p>You can delete individual meetings and recordings in the app, disconnect calendar connections at any time, or contact us to request deletion of your account and associated data.</p>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">Contact us</h2>
        <p>For any privacy questions or data requests, contact us at <a href="mailto:support@antara.cloud" class="text-primary hover:underline">support@antara.cloud</a>.</p>
    </section>
</article>
@endsection
