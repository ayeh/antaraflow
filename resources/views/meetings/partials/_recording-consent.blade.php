{{-- Recording consent gate.

     Shown once per meeting, before the first recording, from inside the
     audioRecorder Alpine component (it reads showConsentGate / consentChecked /
     consentSubmitting / consentError and calls confirmConsent() / cancelConsent()).

     Teleported to <body> so no ancestor transform or overflow can clip a fixed
     overlay, matching the recorder's status pill. It matters most for the
     invisible tab-audio capture: remote participants on Meet/Zoom/Teams see no
     bot, so the person recording carries the whole duty to inform them. --}}
<template x-teleport="body">
    <div x-show="showConsentGate"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         role="dialog"
         aria-modal="true"
         aria-labelledby="recording-consent-title">
        <div class="fixed inset-0 bg-black/50" @click="cancelConsent()"></div>

        <div class="relative w-full max-w-md rounded-xl bg-white dark:bg-slate-800 shadow-xl p-5"
             @keydown.escape.window="cancelConsent()">
            <h3 id="recording-consent-title" class="text-base font-semibold text-gray-900 dark:text-white">
                {{ __('Before you record') }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Let everyone know the meeting is being recorded. You can read this out:') }}
            </p>

            <blockquote class="mt-3 select-all rounded-lg border-l-4 border-teal-500 bg-teal-50 dark:bg-teal-900/20 px-3 py-2 text-sm text-teal-800 dark:text-teal-200">
                {{ __('This meeting is being recorded and transcribed to create the minutes. Please let me know if you would prefer not to be recorded.') }}
            </blockquote>

            <label class="mt-4 flex items-start gap-2 text-sm text-gray-700 dark:text-gray-200 cursor-pointer">
                <input type="checkbox" x-model="consentChecked"
                       class="mt-0.5 h-4 w-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                <span>{{ __('I confirm I have the right to record this meeting and will inform all participants.') }}</span>
            </label>

            <div x-show="consentError" x-cloak
                 class="mt-2 text-xs text-red-600 dark:text-red-400"
                 x-text="consentError">
            </div>

            <div class="mt-5 flex items-center justify-end gap-2">
                <button type="button" @click="cancelConsent()"
                        class="px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">
                    {{ __('Cancel') }}
                </button>
                <button type="button" @click="confirmConsent()"
                        :disabled="!consentChecked || consentSubmitting"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white bg-red-600 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    <svg x-show="consentSubmitting" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span>{{ __("I've informed everyone — Start") }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
