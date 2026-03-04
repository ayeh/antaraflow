{{-- Step 3: Inputs (Audio, Documents, Manual Notes) --}}
<div
    x-data="{
        activeTab: 'all',
        addMode: 'audio',
        manualNoteText: '',
        loading: false,
        successMessage: '',
        errorMessage: '',
        transcriptions: @js($meeting->transcriptions->toArray()),
        manualNotes: @js($meeting->manualNotes->load('createdBy')->toArray()),
        inputs: @js($meeting->inputs->toArray()),

        get filteredItems() {
            switch (this.activeTab) {
                case 'audio':
                    return this.transcriptions;
                case 'documents':
                    return this.inputs;
                case 'notes':
                    return this.manualNotes;
                default:
                    return [
                        ...this.transcriptions.map(t => ({ ...t, _type: 'audio' })),
                        ...this.inputs.map(i => ({ ...i, _type: 'document' })),
                        ...this.manualNotes.map(n => ({ ...n, _type: 'note' })),
                    ];
            }
        },

        get stats() {
            return {
                total: this.transcriptions.length + this.inputs.length + this.manualNotes.length,
                audio: this.transcriptions.length,
                documents: this.inputs.length,
                notes: this.manualNotes.length,
                processed: this.transcriptions.filter(t => t.status === 'completed').length
                    + this.inputs.filter(i => i.status === 'completed' || i.status === 'processed').length,
            };
        },

        csrfToken() {
            return document.querySelector('meta[name=csrf-token]')?.content || '';
        },

        formatSize(bytes) {
            if (!bytes) return '';
            const units = ['B', 'KB', 'MB', 'GB'];
            let i = 0;
            let size = bytes;
            while (size >= 1024 && i < units.length - 1) { size /= 1024; i++; }
            return size.toFixed(1) + ' ' + units[i];
        },

        formatDuration(seconds) {
            if (!seconds) return '';
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return mins + ':' + String(secs).padStart(2, '0');
        },

        statusClasses(status) {
            const map = {
                pending: 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300',
                processing: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
                completed: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
                processed: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
                failed: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300',
            };
            return map[status] || map.pending;
        },

        async uploadAudio(event) {
            const files = event.target.files;
            if (!files || files.length === 0) return;

            this.loading = true;
            this.errorMessage = '';
            this.successMessage = '';

            const formData = new FormData();
            formData.append('audio_file', files[0]);

            try {
                const response = await fetch('{{ route('meetings.transcriptions.store', $meeting) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                if (response.ok) {
                    const data = await response.json().catch(() => null);
                    if (data?.transcription || data?.data) {
                        this.transcriptions.push(data.transcription || data.data);
                    } else {
                        this.transcriptions.push({
                            id: Date.now(),
                            original_filename: files[0].name,
                            file_size: files[0].size,
                            status: 'processing',
                            created_at: new Date().toISOString(),
                        });
                    }
                    this.successMessage = 'Audio uploaded. Transcription processing will begin shortly.';
                    setTimeout(() => this.successMessage = '', 4000);
                } else {
                    const data = await response.json().catch(() => null);
                    this.errorMessage = data?.message || 'Failed to upload audio file.';
                    setTimeout(() => this.errorMessage = '', 4000);
                }
            } catch (e) {
                console.error('Upload failed:', e);
                this.errorMessage = 'Network error. Please try again.';
                setTimeout(() => this.errorMessage = '', 4000);
            }

            this.loading = false;
            event.target.value = '';
        },

        async saveManualNote() {
            if (!this.manualNoteText.trim()) return;

            this.loading = true;
            this.errorMessage = '';
            this.successMessage = '';

            try {
                const response = await fetch('{{ route('meetings.manual-notes.store', $meeting) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ content: this.manualNoteText }),
                });

                if (response.ok || response.redirected) {
                    const data = await response.json().catch(() => null);
                    this.manualNotes.push(data?.note || data?.data || {
                        id: Date.now(),
                        content: this.manualNoteText,
                        created_by: {{ auth()->id() }},
                        created_at: new Date().toISOString(),
                    });
                    this.manualNoteText = '';
                    this.successMessage = 'Note saved successfully.';
                    setTimeout(() => this.successMessage = '', 3000);
                } else {
                    const data = await response.json().catch(() => null);
                    this.errorMessage = data?.message || 'Failed to save note.';
                    setTimeout(() => this.errorMessage = '', 4000);
                }
            } catch (e) {
                console.error('Save note failed:', e);
                this.errorMessage = 'Network error. Please try again.';
                setTimeout(() => this.errorMessage = '', 4000);
            }

            this.loading = false;
        },

        async deleteTranscription(id) {
            if (!confirm('Remove this transcription?')) return;
            this.loading = true;

            try {
                const response = await fetch('{{ url('/meetings/' . $meeting->id . '/transcriptions') }}/' + id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                });

                if (response.ok || response.redirected) {
                    this.transcriptions = this.transcriptions.filter(t => t.id !== id);
                    this.successMessage = 'Transcription removed.';
                    setTimeout(() => this.successMessage = '', 3000);
                }
            } catch (e) {
                console.error('Delete failed:', e);
            }

            this.loading = false;
        },

        async deleteManualNote(id) {
            if (!confirm('Remove this note?')) return;
            this.loading = true;

            try {
                const response = await fetch('{{ url('/meetings/' . $meeting->id . '/manual-notes') }}/' + id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                });

                if (response.ok || response.redirected) {
                    this.manualNotes = this.manualNotes.filter(n => n.id !== id);
                    this.successMessage = 'Note removed.';
                    setTimeout(() => this.successMessage = '', 3000);
                }
            } catch (e) {
                console.error('Delete failed:', e);
            }

            this.loading = false;
        },

        handleRecordingComplete(detail) {
            if (detail?.transcription) {
                this.transcriptions.push(detail.transcription);
                this.successMessage = 'Browser recording uploaded. Transcription processing...';
                setTimeout(() => this.successMessage = '', 4000);
            }
        },
    }"
    @recording-complete.window="handleRecordingComplete($event.detail)"
    class="space-y-6"
>
    {{-- Flash Messages --}}
    <div x-show="successMessage" x-transition x-cloak class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-3">
        <div class="flex items-center gap-2">
            <svg class="h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <p class="text-sm text-green-700 dark:text-green-300" x-text="successMessage"></p>
        </div>
    </div>

    <div x-show="errorMessage" x-transition x-cloak class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-3">
        <div class="flex items-center gap-2">
            <svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <p class="text-sm text-red-700 dark:text-red-300" x-text="errorMessage"></p>
        </div>
    </div>

    {{-- Stats Row --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Total Inputs</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1" x-text="stats.total"></p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Processed</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1" x-text="stats.processed"></p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Audio Files</p>
            <p class="text-2xl font-bold text-violet-600 dark:text-violet-400 mt-1" x-text="stats.audio"></p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Documents</p>
            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1" x-text="stats.documents"></p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Notes</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1" x-text="stats.notes"></p>
        </div>
    </div>

    {{-- Two-Column Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left Panel: Input List (2/3 width) --}}
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
            {{-- Header --}}
            <div class="p-6 pb-4">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Inputs</h2>
                    <span class="text-sm text-gray-500 dark:text-gray-400" x-text="stats.total + ' total'"></span>
                </div>

                {{-- Filter Tabs --}}
                <div class="flex gap-1 bg-gray-100 dark:bg-gray-700/50 rounded-lg p-1">
                    <template x-for="tab in [{key: 'all', label: 'All'}, {key: 'audio', label: 'Audio'}, {key: 'documents', label: 'Documents'}, {key: 'notes', label: 'Notes'}]" :key="tab.key">
                        <button
                            type="button"
                            @click="activeTab = tab.key"
                            :class="activeTab === tab.key
                                ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm'
                                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                            class="flex-1 px-3 py-1.5 text-sm font-medium rounded-md transition-colors"
                            x-text="tab.label"
                        ></button>
                    </template>
                </div>
            </div>

            {{-- Input List --}}
            <div class="px-6 pb-6">
                {{-- Empty State --}}
                <template x-if="filteredItems.length === 0">
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                        </svg>
                        <h3 class="mt-3 text-sm font-medium text-gray-900 dark:text-white">No inputs yet</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Upload audio files, documents, or add manual notes using the panel on the right.</p>
                    </div>
                </template>

                {{-- Item Cards --}}
                <template x-if="filteredItems.length > 0">
                    <div class="space-y-2">
                        {{-- Audio items --}}
                        <template x-for="item in (activeTab === 'audio' || activeTab === 'all') ? transcriptions : []" :key="'t-' + item.id">
                            <div class="flex items-center justify-between p-3 rounded-lg border border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="flex-shrink-0 h-10 w-10 rounded-lg bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center">
                                        <svg class="h-5 w-5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="item.original_filename || 'Audio Recording'"></p>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-xs text-gray-500 dark:text-gray-400" x-text="formatSize(item.file_size)"></span>
                                            <span x-show="item.duration_seconds" class="text-xs text-gray-500 dark:text-gray-400" x-text="formatDuration(item.duration_seconds)" x-cloak></span>
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium" :class="statusClasses(item.status)" x-text="item.status || 'pending'"></span>
                                        </div>
                                    </div>
                                </div>
                                @if($isEditable)
                                    <button
                                        type="button"
                                        @click="deleteTranscription(item.id)"
                                        :disabled="loading"
                                        class="flex-shrink-0 p-1.5 text-gray-400 hover:text-red-500 dark:hover:text-red-400 rounded-md hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors disabled:opacity-50"
                                        title="Remove"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </template>

                        {{-- Document items --}}
                        <template x-for="item in (activeTab === 'documents' || activeTab === 'all') ? inputs : []" :key="'i-' + item.id">
                            <div class="flex items-center justify-between p-3 rounded-lg border border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="flex-shrink-0 h-10 w-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                        <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="item.original_filename || item.name || 'Document'"></p>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-xs text-gray-500 dark:text-gray-400" x-text="formatSize(item.file_size)"></span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400" x-text="item.type || 'document'"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Manual note items --}}
                        <template x-for="item in (activeTab === 'notes' || activeTab === 'all') ? manualNotes : []" :key="'n-' + item.id">
                            <div class="flex items-center justify-between p-3 rounded-lg border border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <div class="flex-shrink-0 h-10 w-10 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                                        <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm text-gray-900 dark:text-white line-clamp-2" x-text="item.content"></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5" x-text="item.created_by_name || (item.created_by_user ? item.created_by_user.name : '')"></p>
                                    </div>
                                </div>
                                @if($isEditable)
                                    <button
                                        type="button"
                                        @click="deleteManualNote(item.id)"
                                        :disabled="loading"
                                        class="flex-shrink-0 p-1.5 text-gray-400 hover:text-red-500 dark:hover:text-red-400 rounded-md hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors disabled:opacity-50"
                                        title="Remove"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- Right Panel: Add Input (1/3 width) --}}
        @if($isEditable)
            <div class="lg:col-span-1 space-y-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Add Input</h3>

                    {{-- Mode Toggle Pills --}}
                    <div class="flex gap-1 mb-5">
                        <template x-for="mode in [{key: 'audio', label: 'Audio'}, {key: 'document', label: 'Document'}, {key: 'note', label: 'Note'}]" :key="mode.key">
                            <button
                                type="button"
                                @click="addMode = mode.key"
                                :class="addMode === mode.key
                                    ? 'bg-violet-600 text-white'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                                class="flex-1 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors text-center"
                                x-text="mode.label"
                            ></button>
                        </template>
                    </div>

                    {{-- Upload Audio --}}
                    <div x-show="addMode === 'audio'">
                        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center hover:border-violet-400 dark:hover:border-violet-500 transition-colors">
                            <svg class="mx-auto h-10 w-10 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                            </svg>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Drop audio file here or click to browse</p>
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">MP3, WAV, M4A, OGG, WebM (max 200MB)</p>
                            <input
                                type="file"
                                accept=".mp3,.wav,.m4a,.ogg,.webm,audio/*"
                                @change="uploadAudio($event)"
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                :disabled="loading"
                            />
                        </div>

                        {{-- Browser Recording --}}
                        <div
                            x-data="audioRecorder({
                                uploadUrl: '{{ route('meetings.transcriptions.store', $meeting) }}',
                                chunkUrl: '{{ route('meetings.audio-chunks.store', $meeting) }}',
                                finalizeUrl: '{{ route('meetings.audio-chunks.finalize', $meeting) }}',
                                cancelUrl: '{{ route('meetings.audio-chunks.destroy', $meeting) }}',
                                meetingId: {{ $meeting->id }},
                            })"
                            class="mt-4 rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden"
                        >
                            {{-- Recovery Banner --}}
                            <template x-if="hasPendingRecovery">
                                <div class="bg-amber-50 dark:bg-amber-900/20 border-b border-amber-200 dark:border-amber-800 p-3 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <svg class="h-4 w-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                        </svg>
                                        <span class="text-xs text-amber-700 dark:text-amber-300">
                                            Unsaved recording found from <span x-text="new Date(recoveryTimestamp).toLocaleString()"></span>
                                        </span>
                                    </div>
                                    <div class="flex gap-2">
                                        <button @click="recoverRecording()" class="text-xs font-medium text-amber-700 dark:text-amber-300 hover:underline">Recover</button>
                                        <button @click="discardRecovery()" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">Discard</button>
                                    </div>
                                </div>
                            </template>

                            <div class="p-4">
                                {{-- Header --}}
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-6 w-6 rounded-full flex items-center justify-center"
                                             :class="{
                                                 'bg-gray-100 dark:bg-gray-700': state === 'idle',
                                                 'bg-green-100 dark:bg-green-900/30': state === 'ready',
                                                 'bg-red-100 dark:bg-red-900/30': ['recording', 'countdown'].includes(state),
                                                 'bg-amber-100 dark:bg-amber-900/30': state === 'paused',
                                                 'bg-blue-100 dark:bg-blue-900/30': isProcessing,
                                             }">
                                            <div x-show="state === 'recording'"
                                                 class="h-2.5 w-2.5 rounded-full bg-red-500 animate-pulse"></div>
                                            <div x-show="state !== 'recording'"
                                                 class="h-2.5 w-2.5 rounded-full"
                                                 :class="{
                                                     'bg-gray-400': state === 'idle',
                                                     'bg-green-500': state === 'ready' || state === 'complete',
                                                     'bg-red-500': state === 'countdown',
                                                     'bg-amber-500': state === 'paused',
                                                     'bg-blue-500': isProcessing,
                                                 }"></div>
                                        </div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">Browser Recording</p>
                                    </div>

                                    {{-- Timer --}}
                                    <div x-show="['recording', 'paused', 'stopping'].includes(state)"
                                         x-cloak
                                         class="flex items-center gap-1.5 text-sm font-mono"
                                         :class="state === 'paused' ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400'">
                                        <div x-show="state === 'recording'" class="h-1.5 w-1.5 rounded-full bg-red-500 animate-pulse"></div>
                                        <span x-text="formattedTimer"></span>
                                        <span x-show="state === 'paused'" class="text-xs font-sans">(Paused)</span>
                                    </div>
                                </div>

                                {{-- Waveform Canvas --}}
                                <div x-show="!['idle', 'complete'].includes(state)"
                                     x-cloak
                                     class="relative mb-3 rounded-lg overflow-hidden bg-gray-50 dark:bg-gray-700/50"
                                     style="height: 64px;">

                                    <canvas x-ref="waveformCanvas"
                                            width="600"
                                            height="64"
                                            class="w-full h-full"></canvas>

                                    {{-- Countdown Overlay --}}
                                    <div x-show="state === 'countdown'"
                                         x-transition
                                         class="absolute inset-0 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm">
                                        <span x-text="countdownValue"
                                              class="text-3xl font-bold text-white"></span>
                                    </div>
                                </div>

                                {{-- Controls --}}
                                <div class="flex items-center gap-2">
                                    {{-- Start Recording (idle / ready) --}}
                                    <template x-if="['idle', 'ready'].includes(state)">
                                        <button @click="startRecording()"
                                                class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white bg-red-500 hover:bg-red-600 transition-colors">
                                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                                <circle cx="12" cy="12" r="6" />
                                            </svg>
                                            <span x-text="state === 'ready' ? 'Record' : 'Start Recording'"></span>
                                        </button>
                                    </template>

                                    {{-- Pause / Stop (recording) --}}
                                    <template x-if="state === 'recording'">
                                        <div class="flex items-center gap-2">
                                            <button @click="pauseRecording()"
                                                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <rect x="6" y="4" width="4" height="16" />
                                                    <rect x="14" y="4" width="4" height="16" />
                                                </svg>
                                                Pause
                                            </button>
                                            <button @click="stopRecording()"
                                                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-white bg-gray-800 dark:bg-gray-600 hover:bg-gray-900 dark:hover:bg-gray-500 transition-colors">
                                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <rect x="6" y="6" width="12" height="12" rx="1" />
                                                </svg>
                                                Stop
                                            </button>
                                        </div>
                                    </template>

                                    {{-- Resume / Stop (paused) --}}
                                    <template x-if="state === 'paused'">
                                        <div class="flex items-center gap-2">
                                            <button @click="resumeRecording()"
                                                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-white bg-green-500 hover:bg-green-600 transition-colors">
                                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <polygon points="5,3 19,12 5,21" />
                                                </svg>
                                                Resume
                                            </button>
                                            <button @click="stopRecording()"
                                                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-white bg-gray-800 dark:bg-gray-600 hover:bg-gray-900 dark:hover:bg-gray-500 transition-colors">
                                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <rect x="6" y="6" width="12" height="12" rx="1" />
                                                </svg>
                                                Stop
                                            </button>
                                        </div>
                                    </template>

                                    {{-- Processing States --}}
                                    <template x-if="state === 'stopping'">
                                        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                            Finalizing audio...
                                        </div>
                                    </template>

                                    <template x-if="state === 'processing'">
                                        <div class="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400">
                                            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                            Processing audio...
                                        </div>
                                    </template>

                                    {{-- Upload Progress --}}
                                    <template x-if="state === 'uploading'">
                                        <div class="flex-1">
                                            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                                                <span>Uploading...</span>
                                                <span x-text="uploadProgress + '%'"></span>
                                            </div>
                                            <div class="h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                                <div class="h-full rounded-full bg-blue-500 transition-all duration-300"
                                                     :style="{ width: uploadProgress + '%' }"></div>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- Complete --}}
                                    <template x-if="state === 'complete'">
                                        <div class="flex items-center justify-between w-full">
                                            <div class="flex items-center gap-2 text-sm text-green-600 dark:text-green-400">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                                <span x-text="successMessage"></span>
                                            </div>
                                            <button @click="resetRecorder()"
                                                    class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                                Record another
                                            </button>
                                        </div>
                                    </template>

                                    {{-- Error --}}
                                    <template x-if="state === 'error'">
                                        <div class="flex items-center justify-between w-full">
                                            <div class="flex items-center gap-2 text-sm text-red-600 dark:text-red-400">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span x-text="errorMessage" class="text-xs"></span>
                                            </div>
                                            <div class="flex gap-2">
                                                <button @click="retryUpload()"
                                                        x-show="recordedBlob || isLongRecording"
                                                        class="text-xs font-medium text-red-600 dark:text-red-400 hover:underline">
                                                    Retry
                                                </button>
                                                <button @click="resetRecorder()"
                                                        class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                                    Reset
                                                </button>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- Requesting Permission --}}
                                    <template x-if="state === 'requesting_permission'">
                                        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                            <svg class="h-4 w-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                                            </svg>
                                            Please allow microphone access...
                                        </div>
                                    </template>
                                </div>

                                {{-- Long recording indicator --}}
                                <div x-show="isLongRecording && state === 'recording'"
                                     x-cloak
                                     class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                                    Progressive upload active — <span x-text="uploadedChunks"></span> chunks uploaded
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Upload Document --}}
                    <div x-show="addMode === 'document'" x-cloak>
                        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center hover:border-violet-400 dark:hover:border-violet-500 transition-colors relative">
                            <svg class="mx-auto h-10 w-10 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Drop document here or click to browse</p>
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">PDF, Word, Text, Images (max 50MB)</p>
                            <input
                                type="file"
                                accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg"
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                :disabled="loading"
                            />
                        </div>
                        <p class="mt-2 text-xs text-gray-400 dark:text-gray-500 italic">Document upload and AI text extraction coming soon.</p>
                    </div>

                    {{-- Manual Note --}}
                    <div x-show="addMode === 'note'" x-cloak>
                        <div class="space-y-3">
                            <textarea
                                x-model="manualNoteText"
                                rows="6"
                                placeholder="Type your meeting notes here..."
                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-violet-500 focus:border-transparent resize-none"
                            ></textarea>
                            <button
                                type="button"
                                @click="saveManualNote()"
                                :disabled="loading || !manualNoteText.trim()"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-white bg-violet-600 hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                <svg x-show="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-text="loading ? 'Saving...' : 'Save Note'"></span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- AI Extraction Button --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                    <form method="POST" action="{{ route('meetings.extract', $meeting) }}">
                        @csrf
                        <button
                            type="submit"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-white bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 transition-all"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                            Run AI Extraction
                        </button>
                    </form>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 text-center">Generate summary, action items, and decisions from all inputs.</p>
                </div>
            </div>
        @else
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                    <div class="text-center py-4">
                        <svg class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Meeting is not editable</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Inputs can only be added in Draft or In Progress status.</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
