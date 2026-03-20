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
        documents: @js($meeting->documents->toArray()),
        inputs: @js($meeting->inputs->toArray()),

        get filteredItems() {
            switch (this.activeTab) {
                case 'audio':
                    return this.transcriptions;
                case 'documents':
                    return this.documents;
                case 'notes':
                    return this.manualNotes;
                default:
                    return [
                        ...this.transcriptions.map(t => ({ ...t, _type: 'audio' })),
                        ...this.documents.map(d => ({ ...d, _type: 'document' })),
                        ...this.manualNotes.map(n => ({ ...n, _type: 'note' })),
                    ];
            }
        },

        get stats() {
            return {
                total: this.transcriptions.length + this.documents.length + this.manualNotes.length,
                audio: this.transcriptions.length,
                documents: this.documents.length,
                notes: this.manualNotes.length,
                processed: this.transcriptions.filter(t => t.status === 'completed').length
                    + this.documents.filter(d => d.status === 'uploaded').length,
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

        stripHtml(html) {
            if (!html) return '';
            const tmp = document.createElement('div');
            tmp.innerHTML = html;
            return tmp.textContent || tmp.innerText || '';
        },

        statusClasses(status) {
            const map = {
                pending: 'bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300',
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
            formData.append('audio', files[0]);

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
                    const editorEl = document.querySelector('[x-ref=editor]');
                    if (editorEl) editorEl.innerHTML = '';
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

                if (response.ok) {
                    this.transcriptions = this.transcriptions.filter(t => t.id !== id);
                    this.successMessage = 'Transcription removed.';
                    setTimeout(() => this.successMessage = '', 3000);
                } else {
                    this.errorMessage = 'Failed to remove transcription.';
                    setTimeout(() => this.errorMessage = '', 4000);
                }
            } catch (e) {
                console.error('Delete failed:', e);
                this.errorMessage = 'Network error. Please try again.';
                setTimeout(() => this.errorMessage = '', 4000);
            }

            this.loading = false;
        },

        async uploadDocument(event) {
            const files = event.target.files;
            if (!files || files.length === 0) return;

            this.loading = true;
            this.errorMessage = '';
            this.successMessage = '';

            const formData = new FormData();
            formData.append('document', files[0]);

            try {
                const response = await fetch('{{ route('meetings.documents.store', $meeting) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                if (response.ok) {
                    const data = await response.json().catch(() => null);
                    this.documents.push(data?.document || {
                        id: Date.now(),
                        original_filename: files[0].name,
                        file_size: files[0].size,
                        mime_type: files[0].type,
                        status: 'uploaded',
                        created_at: new Date().toISOString(),
                    });
                    this.successMessage = 'Document uploaded successfully.';
                    setTimeout(() => this.successMessage = '', 4000);
                } else {
                    const data = await response.json().catch(() => null);
                    this.errorMessage = data?.message || 'Failed to upload document.';
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

        async deleteDocument(id) {
            if (!confirm('Remove this document?')) return;
            this.loading = true;

            try {
                const response = await fetch('{{ url('/meetings/' . $meeting->id . '/documents') }}/' + id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                });

                if (response.ok) {
                    this.documents = this.documents.filter(d => d.id !== id);
                    this.successMessage = 'Document removed.';
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

                if (response.ok) {
                    this.manualNotes = this.manualNotes.filter(n => n.id !== id);
                    this.successMessage = 'Note removed.';
                    setTimeout(() => this.successMessage = '', 3000);
                } else {
                    this.errorMessage = 'Failed to remove note.';
                    setTimeout(() => this.errorMessage = '', 4000);
                }
            } catch (e) {
                console.error('Delete failed:', e);
                this.errorMessage = 'Network error. Please try again.';
                setTimeout(() => this.errorMessage = '', 4000);
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
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Total Inputs</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1" x-text="stats.total"></p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Processed</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1" x-text="stats.processed"></p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Audio Files</p>
            <p class="text-2xl font-bold text-violet-600 dark:text-violet-400 mt-1" x-text="stats.audio"></p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Documents</p>
            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1" x-text="stats.documents"></p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Notes</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1" x-text="stats.notes"></p>
        </div>
    </div>

    {{-- Two-Column Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left Panel: Input List (2/3 width) --}}
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
            {{-- Header --}}
            <div class="p-6 pb-4">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Inputs</h2>
                    <span class="text-sm text-gray-500 dark:text-gray-400" x-text="stats.total + ' total'"></span>
                </div>

                {{-- Filter Tabs --}}
                <div class="flex gap-1 bg-gray-100 dark:bg-slate-700/50 rounded-lg p-1">
                    <template x-for="tab in [{key: 'all', label: 'All'}, {key: 'audio', label: 'Audio'}, {key: 'documents', label: 'Documents'}, {key: 'notes', label: 'Notes'}]" :key="tab.key">
                        <button
                            type="button"
                            @click="activeTab = tab.key"
                            :class="activeTab === tab.key
                                ? 'bg-white dark:bg-slate-600 text-gray-900 dark:text-white shadow-sm'
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
                            <div class="flex items-center justify-between p-3 rounded-lg border border-gray-100 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
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
                        <template x-for="item in (activeTab === 'documents' || activeTab === 'all') ? documents : []" :key="'d-' + item.id">
                            <div class="flex items-center justify-between p-3 rounded-lg border border-gray-100 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="flex-shrink-0 h-10 w-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                        <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="item.original_filename || 'Document'"></p>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-xs text-gray-500 dark:text-gray-400" x-text="formatSize(item.file_size)"></span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400" x-text="item.mime_type || 'document'"></span>
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium" :class="statusClasses(item.status)" x-text="item.status || 'uploaded'"></span>
                                        </div>
                                    </div>
                                </div>
                                @if($isEditable)
                                    <button
                                        type="button"
                                        @click="deleteDocument(item.id)"
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

                        {{-- Manual note items --}}
                        <template x-for="item in (activeTab === 'notes' || activeTab === 'all') ? manualNotes : []" :key="'n-' + item.id">
                            <div class="flex items-center justify-between p-3 rounded-lg border border-gray-100 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <div class="flex-shrink-0 h-10 w-10 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                                        <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm text-gray-900 dark:text-white line-clamp-2" x-text="stripHtml(item.content)"></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5" x-text="(item.created_by && item.created_by.name) ? item.created_by.name : ''"></p>
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
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Add Input</h3>

                    {{-- Mode Toggle Pills --}}
                    <div class="flex gap-1 mb-5">
                        <template x-for="mode in [{key: 'audio', label: 'Audio'}, {key: 'document', label: 'Document'}, {key: 'note', label: 'Note'}]" :key="mode.key">
                            <button
                                type="button"
                                @click="addMode = mode.key"
                                :class="addMode === mode.key
                                    ? 'bg-violet-600 text-white'
                                    : 'bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                                class="flex-1 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors text-center"
                                x-text="mode.label"
                            ></button>
                        </template>
                    </div>

                    {{-- Upload Audio --}}
                    <div x-show="addMode === 'audio'">
                        <div class="relative border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-lg p-6 text-center hover:border-violet-400 dark:hover:border-violet-500 transition-colors">
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
                            class="mt-4 rounded-lg border border-gray-200 dark:border-slate-600 overflow-hidden"
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
                                                 'bg-gray-100 dark:bg-slate-700': state === 'idle',
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
                                     class="relative mb-3 rounded-lg overflow-hidden bg-gray-50 dark:bg-slate-700/50"
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
                                                class="w-full inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-white bg-red-500 hover:bg-red-600 transition-colors">
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
                                                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <rect x="6" y="4" width="4" height="16" />
                                                    <rect x="14" y="4" width="4" height="16" />
                                                </svg>
                                                Pause
                                            </button>
                                            <button @click="stopRecording()"
                                                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-white bg-gray-800 dark:bg-slate-600 hover:bg-gray-900 dark:hover:bg-gray-500 transition-colors">
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
                                                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-white bg-gray-800 dark:bg-slate-600 hover:bg-gray-900 dark:hover:bg-gray-500 transition-colors">
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
                                            <div class="h-1.5 rounded-full bg-gray-200 dark:bg-slate-700 overflow-hidden">
                                                <div class="h-full rounded-full bg-blue-500 transition-all duration-300"
                                                     :style="{ width: uploadProgress + '%' }"></div>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- Complete --}}
                                    <template x-if="state === 'complete'">
                                        <div class="w-full space-y-2">
                                            <div class="flex items-center gap-2 text-sm text-green-600 dark:text-green-400">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                                <span x-text="successMessage"></span>
                                            </div>
                                            <button @click="resetRecorder()"
                                                    class="w-full inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-white bg-red-500 hover:bg-red-600 transition-colors">
                                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <circle cx="12" cy="12" r="6" />
                                                </svg>
                                                Record Another
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
                        <div class="relative border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-lg p-6 text-center hover:border-violet-400 dark:hover:border-violet-500 transition-colors">
                            <svg class="mx-auto h-10 w-10 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Drop document here or click to browse</p>
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">PDF, Word, Text, Images (max 50MB)</p>
                            <input
                                type="file"
                                accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg"
                                @change="uploadDocument($event)"
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                :disabled="loading"
                            />
                        </div>
                    </div>

                    {{-- Manual Note — Rich Text Editor --}}
                    <div x-show="addMode === 'note'" x-cloak
                         x-data="{
                            isFullscreen: false,
                            showMentions: false,
                            mentionQuery: '',
                            mentionIndex: 0,
                            orgMembers: @js($orgMembers ?? []),

                            get filteredMembers() {
                                if (!this.mentionQuery) return this.orgMembers.slice(0, 6);
                                const q = this.mentionQuery.toLowerCase();
                                return this.orgMembers.filter(m => m.name.toLowerCase().includes(q) || (m.email && m.email.toLowerCase().includes(q))).slice(0, 6);
                            },

                            execCmd(cmd, val = null) {
                                document.execCommand(cmd, false, val);
                                this.$refs.editor.focus();
                            },

                            isActive(cmd) {
                                return document.queryCommandState(cmd);
                            },

                            handleEditorInput() {
                                this.manualNoteText = this.$refs.editor.innerHTML;
                                this.checkMention();
                            },

                            checkMention() {
                                const sel = window.getSelection();
                                if (!sel.rangeCount) return;
                                const range = sel.getRangeAt(0);
                                const textNode = range.startContainer;
                                if (textNode.nodeType !== 3) { this.showMentions = false; return; }
                                const text = textNode.textContent.substring(0, range.startOffset);
                                const match = text.match(/@(\w*)$/);
                                if (match) {
                                    this.mentionQuery = match[1];
                                    this.mentionIndex = 0;
                                    this.showMentions = true;
                                } else {
                                    this.showMentions = false;
                                }
                            },

                            insertMention(member) {
                                const sel = window.getSelection();
                                if (!sel.rangeCount) return;
                                const range = sel.getRangeAt(0);
                                const textNode = range.startContainer;
                                const text = textNode.textContent;
                                const beforeCursor = text.substring(0, range.startOffset);
                                const atPos = beforeCursor.lastIndexOf('@');
                                if (atPos === -1) return;

                                const beforeAt = text.substring(0, atPos);
                                const afterCursor = text.substring(range.startOffset);

                                const mentionSpan = document.createElement('span');
                                mentionSpan.className = 'inline-flex items-center px-1.5 py-0.5 rounded bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 text-xs font-medium';
                                mentionSpan.contentEditable = 'false';
                                mentionSpan.dataset.memberId = member.id;
                                mentionSpan.textContent = '@' + member.name;

                                const parent = textNode.parentNode;
                                const beforeNode = document.createTextNode(beforeAt);
                                const afterNode = document.createTextNode('\u00A0' + afterCursor);

                                parent.insertBefore(beforeNode, textNode);
                                parent.insertBefore(mentionSpan, textNode);
                                parent.insertBefore(afterNode, textNode);
                                parent.removeChild(textNode);

                                const newRange = document.createRange();
                                newRange.setStart(afterNode, 1);
                                newRange.collapse(true);
                                sel.removeAllRanges();
                                sel.addRange(newRange);

                                this.showMentions = false;
                                this.manualNoteText = this.$refs.editor.innerHTML;
                            },

                            handleEditorKeydown(e) {
                                if (this.showMentions) {
                                    if (e.key === 'ArrowDown') { e.preventDefault(); this.mentionIndex = Math.min(this.mentionIndex + 1, this.filteredMembers.length - 1); }
                                    else if (e.key === 'ArrowUp') { e.preventDefault(); this.mentionIndex = Math.max(this.mentionIndex - 1, 0); }
                                    else if (e.key === 'Enter') { e.preventDefault(); if (this.filteredMembers[this.mentionIndex]) this.insertMention(this.filteredMembers[this.mentionIndex]); }
                                    else if (e.key === 'Escape') { this.showMentions = false; }
                                }
                            },

                            toggleFullscreen() {
                                this.isFullscreen = !this.isFullscreen;
                                if (this.isFullscreen) {
                                    document.body.style.overflow = 'hidden';
                                } else {
                                    document.body.style.overflow = '';
                                }
                                this.$nextTick(() => this.$refs.editor.focus());
                            },
                         }"
                         @keydown.escape.window="if(isFullscreen) { toggleFullscreen(); }"
                    >
                        <div :class="isFullscreen ? 'fixed inset-0 z-50 bg-white dark:bg-slate-900 flex flex-col p-6' : 'space-y-3'">
                            {{-- Fullscreen Header --}}
                            <div x-show="isFullscreen" class="flex items-center justify-between mb-4" x-cloak>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Note Editor</h2>
                                <button type="button" @click="toggleFullscreen()" class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            {{-- Toolbar --}}
                            <div class="flex flex-wrap items-center gap-0.5 p-1.5 bg-gray-50 dark:bg-slate-700/50 rounded-lg border border-gray-200 dark:border-slate-600">
                                <button type="button" @click="execCmd('bold')" :class="isActive('bold') ? 'bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600'" class="p-1.5 rounded" title="Bold">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z"/><path d="M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z"/></svg>
                                </button>
                                <button type="button" @click="execCmd('italic')" :class="isActive('italic') ? 'bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600'" class="p-1.5 rounded" title="Italic">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg>
                                </button>
                                <button type="button" @click="execCmd('underline')" :class="isActive('underline') ? 'bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600'" class="p-1.5 rounded" title="Underline">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3v7a6 6 0 006 6 6 6 0 006-6V3"/><line x1="4" y1="21" x2="20" y2="21"/></svg>
                                </button>

                                <div class="w-px h-5 bg-gray-300 dark:bg-slate-600 mx-1"></div>

                                <button type="button" @click="execCmd('justifyLeft')" class="p-1.5 rounded text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600" title="Align Left">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="18" y2="18"/></svg>
                                </button>
                                <button type="button" @click="execCmd('justifyCenter')" class="p-1.5 rounded text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600" title="Align Center">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
                                </button>
                                <button type="button" @click="execCmd('justifyRight')" class="p-1.5 rounded text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600" title="Align Right">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="9" y1="12" x2="21" y2="12"/><line x1="6" y1="18" x2="21" y2="18"/></svg>
                                </button>

                                <div class="w-px h-5 bg-gray-300 dark:bg-slate-600 mx-1"></div>

                                <button type="button" @click="execCmd('insertUnorderedList')" class="p-1.5 rounded text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600" title="Bullet List">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4" cy="6" r="1.5" fill="currentColor"/><circle cx="4" cy="12" r="1.5" fill="currentColor"/><circle cx="4" cy="18" r="1.5" fill="currentColor"/></svg>
                                </button>
                                <button type="button" @click="execCmd('insertOrderedList')" class="p-1.5 rounded text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600" title="Numbered List">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="10" y1="6" x2="20" y2="6"/><line x1="10" y1="12" x2="20" y2="12"/><line x1="10" y1="18" x2="20" y2="18"/><text x="2" y="8" fill="currentColor" font-size="7" font-weight="bold" style="font-family:sans-serif">1</text><text x="2" y="14" fill="currentColor" font-size="7" font-weight="bold" style="font-family:sans-serif">2</text><text x="2" y="20" fill="currentColor" font-size="7" font-weight="bold" style="font-family:sans-serif">3</text></svg>
                                </button>
                                <button type="button" @click="execCmd('formatBlock', 'blockquote')" class="p-1.5 rounded text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600" title="Blockquote">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M4.583 17.321C3.553 16.227 3 15 3 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311C9.591 11.69 11 13.21 11 15c0 1.854-1.5 3.36-3.354 3.36-1.137 0-2.213-.537-3.063-1.039zM14.583 17.321C13.553 16.227 13 15 13 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311C19.591 11.69 21 13.21 21 15c0 1.854-1.5 3.36-3.354 3.36-1.137 0-2.213-.537-3.063-1.039z"/></svg>
                                </button>

                                <div class="flex-1"></div>

                                <button type="button" @click="toggleFullscreen()" class="p-1.5 rounded text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600" :title="isFullscreen ? 'Exit Fullscreen' : 'Fullscreen'">
                                    <svg x-show="!isFullscreen" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                                    <svg x-show="isFullscreen" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 4v4a1 1 0 01-1 1H4m16 0h-4a1 1 0 01-1-1V4m0 16v-4a1 1 0 011-1h4M4 15h4a1 1 0 011 1v4"/></svg>
                                </button>
                            </div>

                            {{-- Editor Area --}}
                            <div class="relative" :class="isFullscreen ? 'flex-1 flex flex-col' : ''">
                                <div
                                    x-ref="editor"
                                    contenteditable="true"
                                    @input="handleEditorInput()"
                                    @keydown="handleEditorKeydown($event)"
                                    :class="isFullscreen ? 'flex-1 min-h-[300px]' : 'min-h-[150px]'"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none overflow-y-auto prose prose-sm dark:prose-invert max-w-none [&_blockquote]:border-l-4 [&_blockquote]:border-violet-300 [&_blockquote]:pl-4 [&_blockquote]:italic [&_blockquote]:text-gray-600 [&_blockquote]:dark:text-gray-400"
                                    data-placeholder="Type your meeting notes... Use @ to mention team members"
                                    style="empty-cells:show"
                                ></div>

                                {{-- @Mention Dropdown --}}
                                <div x-show="showMentions" x-cloak
                                     class="absolute left-0 right-0 mt-1 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg shadow-lg z-50 max-h-48 overflow-y-auto"
                                >
                                    <template x-for="(member, idx) in filteredMembers" :key="member.id">
                                        <button type="button"
                                                @click="insertMention(member)"
                                                :class="idx === mentionIndex ? 'bg-violet-50 dark:bg-violet-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-700'"
                                                class="w-full flex items-center gap-3 px-3 py-2 text-left transition-colors"
                                        >
                                            <div class="flex-shrink-0 h-7 w-7 rounded-full bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center text-xs font-medium text-violet-700 dark:text-violet-300" x-text="member.name.charAt(0).toUpperCase()"></div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="member.name"></p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate" x-text="member.email"></p>
                                            </div>
                                        </button>
                                    </template>
                                    <template x-if="filteredMembers.length === 0">
                                        <p class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">No members found</p>
                                    </template>
                                </div>
                            </div>

                            {{-- Save Button --}}
                            <button
                                type="button"
                                @click="saveManualNote()"
                                :disabled="loading || !manualNoteText.trim()"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-white bg-violet-600 hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                :class="isFullscreen ? 'mt-4' : ''"
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

                {{-- Generate MOM Button --}}
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4"
                     x-data="{
                        generating: false,
                        generationProgress: 0,
                        generationStep: '',
                        generationError: '',
                        progressInterval: null,

                        async generateMom() {
                            this.generating = true;
                            this.generationProgress = 0;
                            this.generationStep = 'Starting...';
                            this.generationError = '';

                            const steps = [
                                { progress: 20, step: 'Analyzing meeting content...' },
                                { progress: 40, step: 'Generating summary...' },
                                { progress: 60, step: 'Extracting action items...' },
                                { progress: 80, step: 'Extracting decisions...' },
                            ];
                            let stepIndex = 0;

                            this.progressInterval = setInterval(() => {
                                if (stepIndex < steps.length) {
                                    this.generationProgress = steps[stepIndex].progress;
                                    this.generationStep = steps[stepIndex].step;
                                    stepIndex++;
                                }
                            }, 2000);

                            try {
                                const response = await fetch('{{ route('meetings.generate', $meeting) }}', {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                                        'Accept': 'application/json',
                                    },
                                });

                                clearInterval(this.progressInterval);
                                const data = await response.json();

                                if (data.success) {
                                    this.generationProgress = 100;
                                    this.generationStep = 'Complete!';

                                    setTimeout(() => {
                                        window.location.href = data.redirect_url;
                                    }, 1000);
                                } else {
                                    this.generationError = data.message || 'Generation failed. Please try again.';
                                    this.generating = false;
                                    this.generationProgress = 0;
                                    this.generationStep = '';
                                }
                            } catch (e) {
                                clearInterval(this.progressInterval);
                                this.generationError = 'Network error. Please try again.';
                                this.generating = false;
                                this.generationProgress = 0;
                                this.generationStep = '';
                            }
                        },
                     }"
                >
                    <button
                        type="button"
                        @click="generateMom()"
                        :disabled="generating"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-white bg-gradient-to-r from-violet-600 to-violet-700 hover:from-violet-700 hover:to-violet-800 transition-all disabled:opacity-70 disabled:cursor-not-allowed"
                    >
                        <template x-if="!generating">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                        </template>
                        <template x-if="generating">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </template>
                        <span x-text="generating ? 'Generating...' : 'Generate MOM'"></span>
                    </button>

                    {{-- Progress Bar --}}
                    <div x-show="generating" x-cloak class="mt-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg p-3">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs font-medium text-violet-700 dark:text-violet-300" x-text="generationStep"></span>
                            <span class="text-xs font-medium text-violet-700 dark:text-violet-300" x-text="Math.round(generationProgress) + '%'"></span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-slate-600 rounded-full h-2 overflow-hidden">
                            <div class="bg-gradient-to-r from-violet-500 to-violet-600 h-2 rounded-full transition-all duration-500 ease-out" :style="'width: ' + generationProgress + '%'"></div>
                        </div>
                    </div>

                    {{-- Error State --}}
                    <div x-show="generationError" x-cloak class="mt-3 flex items-center gap-2 text-red-600 dark:text-red-400">
                        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                        <span class="text-xs font-medium" x-text="generationError"></span>
                    </div>

                    <p x-show="!generating && !generationError" class="mt-2 text-xs text-gray-500 dark:text-gray-400 text-center">Generate summary, action items, and decisions from all inputs.</p>
                </div>
            </div>
        @else
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
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
