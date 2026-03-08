export default function liveMeetingDashboard(config) {
    return {
        // Config
        meetingId: config.meetingId,
        sessionId: config.sessionId,
        stateUrl: config.stateUrl,
        chunkUrl: config.chunkUrl,
        endUrl: config.endUrl,
        meetingUrl: config.meetingUrl,

        // Transcript state
        transcriptChunks: config.initialChunks || [],
        isAutoScroll: true,

        // Extraction state
        extractions: config.initialExtractions || [],
        lastExtractionUpdate: null,
        isExtracting: false,

        // Session state
        sessionStatus: config.sessionStatus || 'active',
        startedAt: config.startedAt,
        elapsedSeconds: 0,
        timerInterval: null,
        isEndingSession: false,

        // Attendees
        attendees: config.attendees || [],

        // Speaker color mapping
        speakerColorMap: {},
        speakerColorIndex: 0,
        speakerColors: [
            'text-blue-600 dark:text-blue-400',
            'text-emerald-600 dark:text-emerald-400',
            'text-violet-600 dark:text-violet-400',
            'text-amber-600 dark:text-amber-400',
            'text-rose-600 dark:text-rose-400',
            'text-cyan-600 dark:text-cyan-400',
            'text-fuchsia-600 dark:text-fuchsia-400',
            'text-lime-600 dark:text-lime-400',
        ],
        speakerBgColors: [
            'bg-blue-100 dark:bg-blue-900/30',
            'bg-emerald-100 dark:bg-emerald-900/30',
            'bg-violet-100 dark:bg-violet-900/30',
            'bg-amber-100 dark:bg-amber-900/30',
            'bg-rose-100 dark:bg-rose-900/30',
            'bg-cyan-100 dark:bg-cyan-900/30',
            'bg-fuchsia-100 dark:bg-fuchsia-900/30',
            'bg-lime-100 dark:bg-lime-900/30',
        ],

        // Collapsed sections in extractions panel
        collapsedSections: {},

        init() {
            this.calculateElapsedSeconds();
            this.startTimer();
            this.subscribeToChannels();

            this.$nextTick(() => {
                this.scrollToLatest();
            });
        },

        destroy() {
            this.leaveChannels();
            this.stopTimer();
        },

        // -- Timer --
        calculateElapsedSeconds() {
            if (this.startedAt) {
                const started = new Date(this.startedAt).getTime();
                this.elapsedSeconds = Math.floor((Date.now() - started) / 1000);
            }
        },

        startTimer() {
            if (this.sessionStatus === 'ended') {
                return;
            }

            this.timerInterval = setInterval(() => {
                if (this.sessionStatus === 'active') {
                    this.elapsedSeconds++;
                }
            }, 1000);
        },

        stopTimer() {
            if (this.timerInterval) {
                clearInterval(this.timerInterval);
                this.timerInterval = null;
            }
        },

        formatTime(seconds) {
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;

            return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        },

        // -- Echo Subscriptions --
        subscribeToChannels() {
            if (!window.Echo) {
                return;
            }

            window.Echo.private(`live-meeting.${this.sessionId}`)
                .listen('.TranscriptionChunkProcessed', (e) => {
                    this.handleChunkProcessed(e);
                })
                .listen('.LiveExtractionUpdated', (e) => {
                    this.handleExtractionUpdated(e);
                });

            window.Echo.private(`meeting.${this.meetingId}`)
                .listen('.LiveSessionEnded', (e) => {
                    this.handleSessionEnded(e);
                });
        },

        leaveChannels() {
            if (!window.Echo) {
                return;
            }

            window.Echo.leave(`live-meeting.${this.sessionId}`);
            window.Echo.leave(`meeting.${this.meetingId}`);
        },

        handleChunkProcessed(data) {
            const exists = this.transcriptChunks.find(
                (c) => c.chunk_number === data.chunk_number,
            );

            if (!exists) {
                this.transcriptChunks.push({
                    id: data.chunk_id,
                    chunk_number: data.chunk_number,
                    text: data.text,
                    speaker: data.speaker,
                    start_time: data.start_time,
                    end_time: data.end_time,
                    confidence: data.confidence,
                });

                this.transcriptChunks.sort((a, b) => a.chunk_number - b.chunk_number);

                if (this.isAutoScroll) {
                    this.$nextTick(() => {
                        this.scrollToLatest();
                    });
                }
            }
        },

        handleExtractionUpdated(data) {
            this.extractions = data.extractions;
            this.lastExtractionUpdate = new Date().toISOString();
            this.isExtracting = false;
        },

        handleSessionEnded(data) {
            this.sessionStatus = 'ended';
            this.stopTimer();

            if (data.total_duration_seconds) {
                this.elapsedSeconds = data.total_duration_seconds;
            }
        },

        // -- Transcript Scroll --
        scrollToLatest() {
            const container = this.$refs.transcriptContainer;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },

        handleScroll() {
            const container = this.$refs.transcriptContainer;
            if (!container) {
                return;
            }

            const threshold = 100;
            const distanceFromBottom =
                container.scrollHeight - container.scrollTop - container.clientHeight;
            this.isAutoScroll = distanceFromBottom < threshold;
        },

        // -- Session Controls --
        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        async endSession() {
            if (this.isEndingSession || this.sessionStatus === 'ended') {
                return;
            }

            if (!confirm('Are you sure you want to end this live session? This action cannot be undone.')) {
                return;
            }

            this.isEndingSession = true;

            try {
                const response = await fetch(this.endUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                });

                if (response.ok) {
                    this.sessionStatus = 'ended';
                    this.stopTimer();
                } else {
                    const errorData = await response.json().catch(() => ({}));
                    alert(errorData.error || 'Failed to end session. Please try again.');
                }
            } catch {
                alert('Network error. Please check your connection and try again.');
            } finally {
                this.isEndingSession = false;
            }
        },

        // -- Speaker Colors --
        speakerColor(speaker) {
            if (!speaker) {
                return this.speakerColors[0];
            }

            if (!this.speakerColorMap[speaker]) {
                this.speakerColorMap[speaker] = this.speakerColorIndex;
                this.speakerColorIndex =
                    (this.speakerColorIndex + 1) % this.speakerColors.length;
            }

            return this.speakerColors[this.speakerColorMap[speaker]];
        },

        speakerBgColor(speaker) {
            if (!speaker) {
                return this.speakerBgColors[0];
            }

            if (!this.speakerColorMap[speaker]) {
                this.speakerColorMap[speaker] = this.speakerColorIndex;
                this.speakerColorIndex =
                    (this.speakerColorIndex + 1) % this.speakerBgColors.length;
            }

            return this.speakerBgColors[this.speakerColorMap[speaker]];
        },

        // -- Extraction Helpers --
        getExtractionsByType(type) {
            return this.extractions.filter((e) => e.type === type);
        },

        get hasExtractions() {
            return this.extractions.length > 0;
        },

        get decisions() {
            return this.getExtractionsByType('decisions');
        },

        get actionItems() {
            return this.getExtractionsByType('action_items');
        },

        get topics() {
            return this.getExtractionsByType('topics');
        },

        get summary() {
            return this.getExtractionsByType('summary');
        },

        toggleSection(section) {
            this.collapsedSections[section] = !this.collapsedSections[section];
        },

        isSectionCollapsed(section) {
            return !!this.collapsedSections[section];
        },

        // -- Formatting Helpers --
        formatTimestamp(seconds) {
            if (seconds === null || seconds === undefined) {
                return '';
            }

            const m = Math.floor(seconds / 60);
            const s = Math.floor(seconds % 60);

            return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        },

        formatConfidence(confidence) {
            if (confidence === null || confidence === undefined) {
                return '';
            }

            return Math.round(confidence * 100) + '%';
        },

        formatLastUpdate() {
            if (!this.lastExtractionUpdate) {
                return 'Not yet updated';
            }

            const date = new Date(this.lastExtractionUpdate);
            return date.toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit',
            });
        },

        priorityColor(priority) {
            const colors = {
                high: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300',
                medium: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300',
                low: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
            };

            return colors[priority] || colors.medium;
        },

        get statusLabel() {
            const labels = {
                active: 'Active',
                paused: 'Paused',
                ended: 'Ended',
            };

            return labels[this.sessionStatus] || this.sessionStatus;
        },

        get statusColor() {
            const colors = {
                active: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
                paused: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300',
                ended: 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300',
            };

            return colors[this.sessionStatus] || colors.active;
        },

        get presentCount() {
            return this.attendees.filter((a) => a.is_present).length;
        },
    };
}
