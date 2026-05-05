@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('meetings.show', $meeting) }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">AI Chat &mdash; {{ $meeting->title }}</h1>
        </div>
    </div>

    <div
        x-data="{
            messages: @js($history->map(fn($m) => ['role' => $m->role, 'content' => $m->message, 'time' => $m->created_at->diffForHumans()])->values()),
            newMessage: '',
            loading: false,
            async send() {
                if (!this.newMessage.trim() || this.loading) return;
                const text = this.newMessage;
                this.messages.push({ role: 'user', content: text, time: 'just now' });
                this.newMessage = '';
                this.loading = true;
                this.$nextTick(() => { const el = this.$refs.history; if (el) el.scrollTop = el.scrollHeight; });
                try {
                    const res = await fetch('{{ route('meetings.chat.store', $meeting) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ message: text }),
                    });
                    const data = await res.json();
                    this.messages.push({ role: 'assistant', content: data.message ?? 'No response.', time: 'just now' });
                } catch {
                    this.messages.push({ role: 'assistant', content: 'An error occurred. Please try again.', time: 'just now' });
                } finally {
                    this.loading = false;
                    this.$nextTick(() => { const el = this.$refs.history; if (el) el.scrollTop = el.scrollHeight; });
                }
            }
        }"
        class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 flex flex-col"
        style="height: 70vh;"
    >
        {{-- Message history --}}
        <div x-ref="history" class="flex-1 overflow-y-auto p-6 space-y-4">
            <template x-if="messages.length === 0 && !loading">
                <div class="flex flex-col items-center justify-center h-full text-center">
                    <svg class="w-12 h-12 text-gray-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">No messages yet. Start a conversation about this meeting.</p>
                </div>
            </template>
            <template x-for="(msg, i) in messages" :key="i">
                <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                    <div class="max-w-[75%]">
                        <div
                            :class="msg.role === 'user'
                                ? 'bg-violet-600 text-white rounded-2xl rounded-br-md'
                                : 'bg-gray-100 dark:bg-slate-700 text-gray-900 dark:text-gray-100 rounded-2xl rounded-bl-md'"
                            class="px-4 py-3 text-sm whitespace-pre-wrap"
                            x-text="msg.content"
                        ></div>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1" :class="msg.role === 'user' ? 'text-right' : ''" x-text="msg.time"></p>
                    </div>
                </div>
            </template>
            <template x-if="loading">
                <div class="flex justify-start">
                    <div class="bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-gray-400 px-4 py-3 rounded-2xl rounded-bl-md text-sm flex items-center gap-2">
                        <span class="flex gap-1">
                            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0ms"></span>
                            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:150ms"></span>
                            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:300ms"></span>
                        </span>
                        Thinking...
                    </div>
                </div>
            </template>
        </div>

        {{-- Input form --}}
        <div class="border-t border-gray-200 dark:border-slate-700 p-4">
            <form @submit.prevent="send()" class="flex gap-3">
                <input
                    x-model="newMessage"
                    @keydown.enter.prevent="send()"
                    type="text"
                    placeholder="Ask about this meeting..."
                    :disabled="loading"
                    class="flex-1 rounded-xl border border-gray-200 dark:border-slate-600 bg-gray-50 dark:bg-slate-900 px-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:opacity-50"
                    autofocus
                />
                <button
                    type="submit"
                    :disabled="loading || !newMessage.trim()"
                    class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    Send
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
