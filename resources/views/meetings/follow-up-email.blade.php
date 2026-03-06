@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('meetings.show', $meeting) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Follow-up Email</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Review and send follow-up for {{ $meeting->title }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('meetings.follow-up-email.send', $meeting) }}" class="space-y-6" x-data="{
        recipients: @json($recipients),
        newRecipient: '',
        addRecipient() {
            const email = this.newRecipient.trim();
            if (email && !this.recipients.includes(email) && email.includes('@')) {
                this.recipients.push(email);
                this.newRecipient = '';
            }
        },
        removeRecipient(index) {
            this.recipients.splice(index, 1);
        }
    }">
        @csrf

        {{-- Recipients --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recipients</h2>

            <div class="flex flex-wrap gap-2 mb-3">
                <template x-for="(email, index) in recipients" :key="index">
                    <span class="inline-flex items-center gap-1.5 bg-violet-100 dark:bg-violet-900/30 text-violet-800 dark:text-violet-300 px-3 py-1 rounded-full text-sm">
                        <input type="hidden" :name="'recipients[' + index + ']'" :value="email">
                        <span x-text="email"></span>
                        <button type="button" @click="removeRecipient(index)" class="text-violet-500 hover:text-violet-700 dark:hover:text-violet-200">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </span>
                </template>
            </div>

            <div class="flex gap-2">
                <input type="email" x-model="newRecipient" @keydown.enter.prevent="addRecipient()" placeholder="Add email address..."
                    class="flex-1 rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                <button type="button" @click="addRecipient()" class="bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors">Add</button>
            </div>
            @error('recipients')
                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Subject --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
            <label for="subject" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subject</label>
            <input type="text" name="subject" id="subject" value="{{ old('subject', $subject) }}"
                class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
            @error('subject')
                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Body --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
            <label for="body" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Body</label>
            <textarea name="body" id="body" rows="16"
                class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none resize-y font-mono">{{ old('body', $body) }}</textarea>
            @error('body')
                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between">
            <a href="{{ route('meetings.show', $meeting) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">Cancel</a>
            <div class="flex items-center gap-3">
                <a href="{{ route('meetings.follow-up-email.generate', $meeting) }}" class="bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">Regenerate</a>
                <button type="submit" class="bg-violet-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    Send Email
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
