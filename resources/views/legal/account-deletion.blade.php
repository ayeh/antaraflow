@extends('layouts.public')

@section('content')
@php($app = $branding->appName())
<article class="space-y-6">
    <header>
        <h1 class="text-3xl font-bold text-gray-900">{{ __('Deleting your account') }}</h1>
        <p class="mt-2 text-sm text-gray-500">{{ __('Last updated:') }} 15 August 2026</p>
    </header>

    <p>{{ __('This page explains how to have your :app account and its data deleted, what is removed, and what is kept. It applies to both the website and the :app mobile app (cloud.antara.note).', ['app' => $app]) }}</p>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">{{ __('How to request deletion') }}</h2>
        <p>{{ __('Email us at') }} <a href="mailto:support@antara.cloud?subject=Account%20deletion%20request" class="text-primary hover:underline">support@antara.cloud</a> {{ __('from the email address on the account, with the subject "Account deletion request".') }}</p>
        <p>{{ __('We reply to confirm it is really you before anything is deleted. Once confirmed, the account is deleted within 30 days, and backup copies age out within a further 30 days.') }}</p>
        <p>{{ __('You do not need the app installed to make the request, and there is no charge.') }}</p>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">{{ __('What is deleted') }}</h2>
        <ul class="list-disc list-inside space-y-1">
            <li>{{ __('Your profile: name, email address, password, and profile photo.') }}</li>
            <li>{{ __('Your sign-in connections, including any linked Google or Microsoft account and the stored tokens for them.') }}</li>
            <li>{{ __('Your devices and their recording sessions, including any audio still queued for upload.') }}</li>
            <li>{{ __('Your notification history and personal preferences.') }}</li>
        </ul>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">{{ __('What is kept, and why') }}</h2>
        <p>{{ __('Meetings, minutes, recordings, and action items belong to the organisation that created them, not to the individual member — they are that organisation\'s official record, and other members rely on them. Deleting your account removes you from the organisation; it does not delete the organisation\'s meeting records. Where those records name you, your name remains in them as a matter of record.') }}</p>
        <p>{{ __('If you administer the organisation and want the organisation itself and all of its meeting records deleted, say so in your email and we will confirm that with you separately.') }}</p>
        <p>{{ __('We also keep the minimum required by law — for example billing and tax records — for as long as the law requires, and no longer.') }}</p>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">{{ __('Deleting individual items instead') }}</h2>
        <p>{{ __('If you only want to remove particular content, you do not need to delete your account. You can delete individual meetings and recordings in the app, and disconnect a calendar connection at any time from your account settings, which revokes and deletes the stored tokens for that connection.') }}</p>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">{{ __('Questions') }}</h2>
        <p>{{ __('See our') }} <a href="{{ route('privacy') }}" class="text-primary hover:underline">{{ __('Privacy Policy') }}</a> {{ __('for how we handle information generally, or write to us at') }} <a href="mailto:support@antara.cloud" class="text-primary hover:underline">support@antara.cloud</a>.</p>
    </section>
</article>
@endsection
