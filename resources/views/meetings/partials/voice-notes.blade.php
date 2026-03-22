{{-- Voice Notes Component --}}
<div x-data="voiceNotes({{ $meeting->id }})" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z" />
            </svg>
            Voice Notes
        </h3>

        {{-- Record Button --}}
        @can('update', $meeting)
        <div class="flex items-center gap-2">
            <template x-if="recording">
                <div class="flex items-center gap-2">
                    <span class="flex items-center gap-1 text-xs text-red-600 dark:text-red-400 font-medium">
                        <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                        <span x-text="formatTime(recordingTime)"></span>
                    </span>
                    <button @click="stopRecording()"
                            class="px-3 py-1.5 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                        Stop
                    </button>
                </div>
            </template>
            <template x-if="!recording">
                <button @click="startRecording()"
                        :disabled="uploading"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-violet-600 dark:text-violet-400 bg-violet-50 dark:bg-violet-900/20 rounded-lg hover:bg-violet-100 dark:hover:bg-violet-900/40 transition-colors disabled:opacity-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z" />
                    </svg>
                    Record Note
                </button>
            </template>
        </div>
        @endcan
    </div>

    {{-- Upload progress --}}
    <template x-if="uploading">
        <div class="mb-3 p-3 bg-violet-50 dark:bg-violet-900/20 rounded-lg">
            <p class="text-xs text-violet-600 dark:text-violet-400 font-medium">Uploading & transcribing...</p>
        </div>
    </template>

    {{-- Error --}}
    <template x-if="error">
        <div class="mb-3 p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
            <p class="text-xs text-red-600 dark:text-red-400" x-text="error"></p>
        </div>
    </template>

    {{-- Notes List --}}
    <div class="space-y-3">
        <template x-if="notes.length === 0 && !uploading">
            <p class="text-xs text-gray-400 dark:text-gray-500 italic">No voice notes yet. Click "Record Note" to add one.</p>
        </template>
        <template x-for="note in notes" :key="note.id">
            <div class="p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-medium text-gray-700 dark:text-gray-300" x-text="note.created_by"></span>
                            <span class="text-xs text-gray-400" x-text="note.created_at"></span>
                            <span class="text-xs text-gray-400" x-text="formatTime(note.duration_seconds)"></span>
                            <span x-show="note.status === 'transcribing'"
                                  class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300">
                                Transcribing...
                            </span>
                            <span x-show="note.status === 'failed'"
                                  class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300">
                                Failed
                            </span>
                        </div>
                        <p x-show="note.transcript" class="text-sm text-gray-600 dark:text-gray-300" x-text="note.transcript"></p>
                        <p x-show="!note.transcript && note.status === 'pending'"
                           class="text-xs text-gray-400 italic">Waiting for transcription...</p>
                    </div>
                    @can('update', $meeting)
                    <button @click="deleteNote(note.id)"
                            class="flex-shrink-0 p-1 text-gray-400 hover:text-red-500 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                    @endcan
                </div>
            </div>
        </template>
    </div>
</div>

<script>
function voiceNotes(meetingId) {
    return {
        notes: [],
        recording: false,
        recordingTime: 0,
        uploading: false,
        error: null,
        mediaRecorder: null,
        audioChunks: [],
        timerInterval: null,
        pollInterval: null,

        init() {
            this.fetchNotes();
            this.pollInterval = setInterval(() => this.pollPending(), 5000);
        },

        destroy() {
            if (this.timerInterval) clearInterval(this.timerInterval);
            if (this.pollInterval) clearInterval(this.pollInterval);
        },

        async fetchNotes() {
            try {
                const resp = await fetch(`/meetings/${meetingId}/voice-notes`);
                const data = await resp.json();
                this.notes = data.data || [];
            } catch (e) {
                console.error('Failed to fetch voice notes:', e);
            }
        },

        async startRecording() {
            try {
                this.error = null;
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                this.mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm' });
                this.audioChunks = [];
                this.recordingTime = 0;

                this.mediaRecorder.ondataavailable = (e) => {
                    if (e.data.size > 0) this.audioChunks.push(e.data);
                };

                this.mediaRecorder.onstop = () => {
                    stream.getTracks().forEach(t => t.stop());
                    this.uploadRecording();
                };

                this.mediaRecorder.start();
                this.recording = true;
                this.timerInterval = setInterval(() => {
                    this.recordingTime++;
                    if (this.recordingTime >= 600) this.stopRecording();
                }, 1000);
            } catch (e) {
                this.error = 'Microphone access denied. Please allow microphone access.';
            }
        },

        stopRecording() {
            if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
                this.mediaRecorder.stop();
            }
            this.recording = false;
            if (this.timerInterval) {
                clearInterval(this.timerInterval);
                this.timerInterval = null;
            }
        },

        async uploadRecording() {
            this.uploading = true;
            try {
                const blob = new Blob(this.audioChunks, { type: 'audio/webm' });
                const formData = new FormData();
                formData.append('audio', blob, 'voice-note.webm');
                formData.append('duration_seconds', this.recordingTime.toString());

                const resp = await fetch(`/meetings/${meetingId}/voice-notes`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
                    body: formData,
                });

                if (!resp.ok) throw new Error('Upload failed');

                await this.fetchNotes();
            } catch (e) {
                this.error = 'Failed to upload voice note. Please try again.';
            } finally {
                this.uploading = false;
                this.audioChunks = [];
            }
        },

        async deleteNote(noteId) {
            if (!confirm('Delete this voice note?')) return;
            try {
                await fetch(`/meetings/${meetingId}/voice-notes/${noteId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        'Accept': 'application/json',
                    },
                });
                this.notes = this.notes.filter(n => n.id !== noteId);
            } catch (e) {
                this.error = 'Failed to delete voice note.';
            }
        },

        async pollPending() {
            const hasPending = this.notes.some(n => ['pending', 'transcribing'].includes(n.status));
            if (hasPending) await this.fetchNotes();
        },

        formatTime(seconds) {
            const m = Math.floor(seconds / 60);
            const s = seconds % 60;
            return `${m}:${s.toString().padStart(2, '0')}`;
        },
    };
}
</script>
