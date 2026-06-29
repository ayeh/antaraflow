@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('meetings.show', $meeting) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Send Agenda</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Review and send the agenda for {{ $meeting->title }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('meetings.agenda-email.send', $meeting) }}" class="space-y-6" x-data="{
        recipients: {{ \Illuminate\Support\Js::from($recipients) }},
        orgMembers: {{ \Illuminate\Support\Js::from($orgMembers) }},
        newRecipient: '',
        showMembers: false,
        memberSearch: '',
        addRecipient() {
            const email = this.newRecipient.trim();
            if (email && !this.recipients.includes(email) && email.includes('@')) {
                this.recipients.push(email);
                this.newRecipient = '';
            }
        },
        removeRecipient(index) {
            this.recipients.splice(index, 1);
        },
        availableMembers() {
            const q = this.memberSearch.trim().toLowerCase();
            return this.orgMembers.filter(m => m.email
                && !this.recipients.includes(m.email)
                && (q === '' || (m.name || '').toLowerCase().includes(q) || m.email.toLowerCase().includes(q)));
        },
        addMember(email) {
            if (email && !this.recipients.includes(email)) {
                this.recipients.push(email);
            }
        },
        addAllMembers() {
            this.availableMembers().forEach(m => this.recipients.push(m.email));
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
                <button type="button" @click="showMembers = !showMembers"
                    class="inline-flex items-center gap-1.5 bg-violet-50 dark:bg-violet-900/20 text-violet-700 dark:text-violet-300 border border-violet-200 dark:border-violet-800 px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-100 dark:hover:bg-violet-900/40 transition-colors whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-3-6.65"/></svg>
                    Import members
                </button>
            </div>

            {{-- Member import panel --}}
            <div x-show="showMembers" x-cloak x-transition class="mt-3 rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900/40 p-3">
                <div class="flex items-center gap-2 mb-2">
                    <input type="text" x-model="memberSearch" placeholder="Search team / organization members..."
                        class="flex-1 rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-3 py-1.5 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                    <button type="button" @click="addAllMembers()" x-show="availableMembers().length > 0"
                        class="text-sm font-medium text-violet-600 dark:text-violet-400 hover:underline whitespace-nowrap">
                        Add all (<span x-text="availableMembers().length"></span>)
                    </button>
                </div>
                <div class="max-h-56 overflow-y-auto divide-y divide-gray-100 dark:divide-slate-700/60">
                    <template x-for="member in availableMembers()" :key="member.id">
                        <button type="button" @click="addMember(member.email)"
                            class="w-full flex items-center justify-between gap-3 px-2 py-2 text-left rounded-md hover:bg-white dark:hover:bg-slate-800 transition-colors">
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-gray-800 dark:text-slate-200 truncate" x-text="member.name || member.email"></span>
                                <span class="block text-xs text-gray-500 dark:text-slate-400 truncate" x-text="member.email"></span>
                            </span>
                            <svg class="w-4 h-4 text-violet-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </button>
                    </template>
                    <p x-show="availableMembers().length === 0" class="px-2 py-3 text-sm text-gray-500 dark:text-slate-400">
                        No members to add.
                    </p>
                </div>
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
                <a href="{{ route('meetings.agenda-email.generate', $meeting) }}" class="bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">Regenerate</a>
                <button type="submit" class="bg-violet-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    Send Agenda
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
