@extends('layouts.public')

@section('content')
@php($app = $branding->appName())
<article class="space-y-6">
    <header>
        <h1 class="text-3xl font-bold text-gray-900">Terms of Service</h1>
        <p class="mt-2 text-sm text-gray-500">Last updated: 2 July 2026</p>
    </header>

    <p>These Terms of Service ("Terms") govern your access to and use of {{ $app }} (the "Service"). By using the Service, you agree to these Terms.</p>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">Use of the Service</h2>
        <p>You may use the Service only in compliance with these Terms and all applicable laws. You are responsible for the content you record, upload, or create, and for obtaining any consent required to record a meeting.</p>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">Your account</h2>
        <p>You are responsible for maintaining the security of your account and for all activity that occurs under it. Notify us promptly of any unauthorized use.</p>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">Third-party connections</h2>
        <p>When you connect third-party services such as Google or Microsoft, your use of those services is also governed by their respective terms. You may disconnect them at any time from your account settings.</p>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">Availability &amp; disclaimer</h2>
        <p>The Service is provided "as is" without warranties of any kind. We do not guarantee that transcripts, minutes, or AI-generated content are accurate or complete, and you should review them before relying on them.</p>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">Changes</h2>
        <p>We may update these Terms from time to time. Continued use of the Service after changes take effect constitutes acceptance of the updated Terms.</p>
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold text-gray-900">Contact</h2>
        <p>Questions about these Terms? Contact us at <a href="mailto:support@antara.cloud" class="text-primary hover:underline">support@antara.cloud</a>.</p>
    </section>
</article>
@endsection
