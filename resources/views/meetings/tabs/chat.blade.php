<div x-data="{
    messages: [],
    newMessage: '',
    loading: false,
    async sendMessage() {
        if (!this.newMessage.trim() || this.loading) return;

        const userMessage = this.newMessage;
        this.messages.push({ role: 'user', content: userMessage });
        this.newMessage = '';
        this.loading = true;

        try {
            const response = await fetch('{{ route('meetings.chat.store', $meeting) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ message: userMessage }),
            });
            const data = await response.json();
            this.messages.push({ role: 'assistant', content: data.response ?? data.message ?? 'No response.' });
        } catch (error) {
            this.messages.push({ role: 'assistant', content: 'An error occurred. Please try again.' });
        } finally {
            this.loading = false;
            this.$nextTick(() => {
                const container = this.$refs.messageContainer;
                if (container) container.scrollTop = container.scrollHeight;
            });
        }
    }
}" class="space-y-4">
    <h3 class="text-lg font-medium text-gray-900">AI Chat</h3>
    <p class="text-sm text-gray-500">Ask questions about this meeting's content, transcriptions, and action items.</p>

    <div x-ref="messageContainer" class="h-96 overflow-y-auto border border-gray-200 rounded-lg p-4 space-y-4 bg-gray-50">
        <template x-if="messages.length === 0">
            <div class="text-center text-sm text-gray-400 py-16">Start a conversation about this meeting.</div>
        </template>
        <template x-for="(msg, index) in messages" :key="index">
            <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                <div :class="msg.role === 'user' ? 'bg-blue-600 text-white' : 'bg-white text-gray-900 border border-gray-200'" class="max-w-[80%] rounded-lg px-4 py-2 text-sm" x-text="msg.content"></div>
            </div>
        </template>
        <template x-if="loading">
            <div class="flex justify-start">
                <div class="bg-white text-gray-500 border border-gray-200 rounded-lg px-4 py-2 text-sm">Thinking...</div>
            </div>
        </template>
    </div>

    <form @submit.prevent="sendMessage()" class="flex gap-3">
        <input x-model="newMessage" type="text" placeholder="Ask about this meeting..." class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none" :disabled="loading">
        <button type="submit" :disabled="loading || !newMessage.trim()" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">Send</button>
    </form>
</div>
