@extends('layouts.public')

@section('content')
@php($app = $branding->appName())
<article class="space-y-6">
    <header>
        <h1 class="text-3xl font-bold text-gray-900">{{ __('Privacy Policy') }}</h1>
        <p class="mt-2 text-sm text-gray-500">{{ __('Last updated:') }} 11 July 2026</p>
    </header>

    <p>{{ __('This Privacy Policy explains how :app ("we", "us") collects, uses, and protects your information when you use our meeting minutes, recording, and transcription service (the "Service").', ['app' => $app]) }}</p>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">{{ __('Information we collect') }}</h2>
        <ul class="list-disc list-inside space-y-1">
            <li><strong>{{ __('Account information') }}</strong> — {{ __('when you sign in with Google or Microsoft, we receive your name, email address, and basic profile to create and secure your account.') }}</li>
            <li><strong>{{ __('Calendar data') }}</strong> — {{ __('if you connect a Google or Microsoft calendar, we access your calendar events to provide the features you enable (see below).') }}</li>
            <li><strong>{{ __('Meeting content') }}</strong> — {{ __('audio you record, transcripts, minutes, action items, and related content you create in the Service.') }}</li>
            <li><strong>{{ __('Usage data') }}</strong> — {{ __('basic technical logs needed to operate and secure the Service.') }}</li>
        </ul>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">{{ __('How we use Google user data') }}</h2>
        <p>{{ __('When you connect a Google account, we request access to your Google Calendar events') }} (<code>calendar.events</code>) {{ __('and the read-only list of your calendars') }} (<code>calendar.calendarlist.readonly</code>), {{ __('and use them solely to:') }}</p>
        <ul class="list-disc list-inside space-y-1">
            <li>{{ __('Read your upcoming events so we can notify you when a meeting is about to start (if you enable "Notify on start").') }}</li>
            <li>{{ __('Create, update, or remove calendar events that correspond to meetings you choose to sync from :app.', ['app' => $app]) }}</li>
        </ul>
        <p>{{ __('We access Google user data only to provide these user-facing features, at your direction. We do not use it for advertising, and we do not sell it.') }}</p>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">{{ __('Limited Use disclosure') }}</h2>
        <p>{{ __(':app\'s use and transfer to any other app of information received from Google APIs will adhere to the', ['app' => $app]) }}
            <a href="https://developers.google.com/terms/api-services-user-data-policy" class="text-primary hover:underline" target="_blank" rel="noopener">Google API Services User Data Policy</a>,
            {{ __('including the Limited Use requirements.') }}</p>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">{{ __('How we share information') }}</h2>
        <p>{{ __('We do not sell your personal information. We share it only with sub-processors that help us operate the Service (for example, our AI transcription and email delivery providers), and only to the extent necessary to provide the Service, or where required by law.') }}</p>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">{{ __('Data protection') }}</h2>
        <p>{{ __('We protect your information using industry-standard security measures, including:') }}</p>
        <ul class="list-disc list-inside space-y-1">
            <li>{{ __('Encrypted transmission via HTTPS/TLS for all data in transit.') }}</li>
            <li>{{ __('OAuth tokens and sensitive credentials stored encrypted at rest.') }}</li>
            <li>{{ __('Access to Google user data is restricted to authorised service components only.') }}</li>
            <li>{{ __('We do not log or cache raw Google API responses beyond what is necessary to serve the request.') }}</li>
        </ul>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">{{ __('AI and machine learning') }}</h2>
        <p>{{ __(':app uses AI to generate transcripts and meeting minutes from audio you record. Google user data — including your calendar events, calendar list, and account profile — is not used to develop, improve, or train any AI or machine learning model, whether by us or by any third-party provider.', ['app' => $app]) }}</p>
        <p>{{ __('Our AI transcription provider processes only audio content that you explicitly submit for transcription. It does not receive Google Calendar data or other Google user data, and is contractually prohibited from using any user-submitted content to train its own models.') }}</p>
        <p>{{ __('The use of information received from Google APIs will adhere to the') }}
            <a href="https://developers.google.com/terms/api-services-user-data-policy" class="text-primary hover:underline" target="_blank" rel="noopener">Google API Services User Data Policy</a>,
            {{ __('including the Limited Use requirements and AI/ML training restrictions.') }}</p>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">{{ __('Data storage & retention') }}</h2>
        <p>{{ __('OAuth tokens are stored encrypted at rest. We retain your content for as long as your account is active. You can disconnect a calendar at any time from your account settings, which revokes and deletes the stored tokens for that connection.') }}</p>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">{{ __('Deleting your data') }}</h2>
        <p>{{ __('You can delete individual meetings and recordings in the app, disconnect calendar connections at any time, or contact us to request deletion of your account and associated data.') }}</p>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">{{ __('Contact us') }}</h2>
        <p>{{ __('For any privacy questions or data requests, contact us at') }} <a href="mailto:support@antara.cloud" class="text-primary hover:underline">support@antara.cloud</a>.</p>
    </section>
</article>
@endsection
