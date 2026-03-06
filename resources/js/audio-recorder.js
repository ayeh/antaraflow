export default function audioRecorder(config) {
    return {
        // Config (passed from blade)
        uploadUrl: config.uploadUrl,
        chunkUrl: config.chunkUrl,
        finalizeUrl: config.finalizeUrl,
        cancelUrl: config.cancelUrl,
        meetingId: config.meetingId,

        // State machine
        state: 'idle', // idle, requesting_permission, ready, countdown, recording, paused, stopping, processing, uploading, complete, error

        // Recording data
        mediaRecorder: null,
        mediaStream: null,
        chunks: [],
        sessionId: null,
        mimeType: null,
        recordedBlob: null,

        // UI state
        timer: 0,
        timerInterval: null,
        countdownValue: 3,
        countdownInterval: null,
        uploadProgress: 0,
        errorMessage: '',
        successMessage: '',
        canvasContext: null,
        animationFrame: null,
        showCountdown: true,

        // Chunk upload tracking
        chunkIndex: 0,
        isLongRecording: false,
        chunkInterval: null,
        uploadedChunks: 0,

        // Recovery
        hasPendingRecovery: false,
        recoveryTimestamp: null,

        // Computed
        get formattedTimer() {
            const hours = Math.floor(this.timer / 3600);
            const mins = Math.floor((this.timer % 3600) / 60);
            const secs = this.timer % 60;
            if (hours > 0) {
                return `${hours}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
            }
            return `${mins}:${String(secs).padStart(2, '0')}`;
        },

        get isRecording() {
            return this.state === 'recording';
        },

        get isPaused() {
            return this.state === 'paused';
        },

        get isProcessing() {
            return ['stopping', 'processing', 'uploading'].includes(this.state);
        },

        // -- Lifecycle --
        init() {
            this.detectMimeType();
            this.checkPendingRecovery();
            this.checkExistingPermission();

            this.$nextTick(() => {
                const canvas = this.$refs.waveformCanvas;
                if (canvas) {
                    this.canvasContext = canvas.getContext('2d');
                }
            });
        },

        destroy() {
            this.cleanup();
        },

        // -- MIME Type Detection --
        detectMimeType() {
            const types = [
                'audio/webm;codecs=opus',
                'audio/webm',
                'audio/mp4',
                'audio/ogg;codecs=opus',
            ];

            for (const type of types) {
                if (typeof MediaRecorder !== 'undefined' && MediaRecorder.isTypeSupported(type)) {
                    this.mimeType = type;
                    return;
                }
            }
            this.mimeType = 'audio/webm';
        },

        // -- Permission Handling --
        async checkExistingPermission() {
            try {
                if (navigator.permissions?.query) {
                    const result = await navigator.permissions.query({ name: 'microphone' });
                    if (result.state === 'granted') {
                        await this.setupStream();
                        this.state = 'ready';
                    }
                }
            } catch {
                // Firefox doesn't support microphone permission query
            }
        },

        async requestPermission() {
            this.state = 'requesting_permission';
            this.errorMessage = '';

            try {
                await this.setupStream();
                this.state = 'ready';
            } catch (err) {
                this.handlePermissionError(err);
            }
        },

        handlePermissionError(err) {
            this.state = 'error';

            if (err.name === 'NotAllowedError') {
                this.errorMessage = 'Microphone access denied. Please allow microphone access in your browser settings.';
            } else if (err.name === 'NotFoundError') {
                this.errorMessage = 'No microphone found. Please connect a microphone and try again.';
            } else if (err.name === 'NotReadableError') {
                this.errorMessage = 'Microphone is in use by another application. Please close it and try again.';
            } else {
                this.errorMessage = 'Could not access microphone. Please try again.';
            }
        },

        // -- Stream Setup --
        async setupStream() {
            this.mediaStream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                },
            });

            this.drawWaveform();
        },

        // -- Waveform Visualization (time-based animation) --
        drawWaveform() {
            // Re-acquire canvas context if not yet available (canvas may have been hidden during init)
            if (!this.canvasContext) {
                const canvas = this.$refs.waveformCanvas;
                if (canvas) {
                    this.canvasContext = canvas.getContext('2d');
                }
            }

            if (!this.canvasContext) {
                this.animationFrame = requestAnimationFrame(() => this.drawWaveform());
                return;
            }

            const canvas = this.$refs.waveformCanvas;
            if (!canvas) return;

            const ctx = this.canvasContext;
            const width = canvas.width;
            const height = canvas.height;
            const centerY = height / 2;
            const time = performance.now() / 1000;

            // Clear canvas
            ctx.fillStyle = getComputedStyle(document.documentElement)
                .getPropertyValue('--color-gray-50')?.trim() || '#f9fafb';
            if (document.documentElement.classList.contains('dark')) {
                ctx.fillStyle = 'rgba(55, 65, 81, 0.3)';
            }
            ctx.fillRect(0, 0, width, height);

            // Bar color by state
            if (this.state === 'recording') {
                ctx.fillStyle = '#ef4444';
            } else if (this.state === 'ready' || this.state === 'countdown') {
                ctx.fillStyle = '#22c55e';
            } else if (this.state === 'paused') {
                ctx.fillStyle = '#f59e0b';
            } else {
                ctx.fillStyle = '#9ca3af';
            }

            // Volume level: animate when recording, gentle pulse when ready/paused, flat otherwise
            let vol;
            if (this.state === 'recording') {
                vol = 0.6;
            } else if (this.state === 'paused') {
                vol = 0.15 + Math.sin(time * 1.5) * 0.05;
            } else if (this.state === 'ready' || this.state === 'countdown') {
                vol = 0.1 + Math.sin(time * 2) * 0.05;
            } else {
                vol = 0;
            }

            // Draw animated bars
            const barCount = 40;
            const barWidth = Math.max(2, (width / barCount) * 0.6);
            const gap = width / barCount;

            for (let i = 0; i < barCount; i++) {
                const x = i * gap + (gap - barWidth) / 2;

                // Layered sine waves for natural-looking waveform movement
                const wave1 = Math.sin(time * 3.0 + i * 0.4) * 0.3;
                const wave2 = Math.sin(time * 5.7 + i * 0.7) * 0.2;
                const wave3 = Math.sin(time * 2.3 + i * 0.15) * 0.5;
                const envelope = Math.sin((i / barCount) * Math.PI); // Taper at edges

                const baseHeight = 2;
                const maxHeight = (height / 2) - 4;
                const barHeight = baseHeight + (wave1 + wave2 + wave3) * envelope * vol * maxHeight;
                const h = Math.max(baseHeight, Math.abs(barHeight));

                // Symmetric bar from center
                const radius = Math.min(barWidth / 2, h / 2);
                ctx.beginPath();
                ctx.roundRect(x, centerY - h, barWidth, h * 2, radius);
                ctx.fill();
            }

            if (!['idle', 'complete', 'error'].includes(this.state)) {
                this.animationFrame = requestAnimationFrame(() => this.drawWaveform());
            }
        },

        // -- Recording Controls --
        async startRecording() {
            if (this.state === 'idle') {
                await this.requestPermission();
                if (this.state !== 'ready') return;
            }

            if (this.showCountdown) {
                this.startCountdown();
            } else {
                this.beginRecording();
            }
        },

        startCountdown() {
            this.state = 'countdown';
            this.countdownValue = 3;

            this.countdownInterval = setInterval(() => {
                this.countdownValue--;
                if (this.countdownValue <= 0) {
                    clearInterval(this.countdownInterval);
                    this.beginRecording();
                }
            }, 1000);
        },

        beginRecording() {
            this.chunks = [];
            this.chunkIndex = 0;
            this.uploadedChunks = 0;
            this.timer = 0;
            this.isLongRecording = false;
            this.sessionId = crypto.randomUUID();
            this.errorMessage = '';

            this.mediaRecorder = new MediaRecorder(this.mediaStream, {
                mimeType: this.mimeType,
            });

            this.mediaRecorder.ondataavailable = (e) => {
                if (e.data.size > 0) {
                    this.chunks.push(e.data);

                    if (this.isLongRecording) {
                        this.uploadChunk(e.data, this.chunkIndex);
                        this.chunkIndex++;
                    }
                }
            };

            this.mediaRecorder.onstop = () => {
                this.handleRecordingStop();
            };

            this.mediaRecorder.onerror = (e) => {
                console.error('MediaRecorder error:', e);
                this.state = 'error';
                this.errorMessage = 'Recording failed. Please try again.';
                this.cleanup();
            };

            this.mediaRecorder.start();
            this.state = 'recording';
            this.playBeep(300, 200);

            this.timerInterval = setInterval(() => {
                this.timer++;

                if (this.timer === 300 && !this.isLongRecording) {
                    this.switchToChunkedMode();
                }
            }, 1000);

            this.drawWaveform();
        },

        switchToChunkedMode() {
            this.isLongRecording = true;

            if (this.mediaRecorder?.state === 'recording') {
                this.mediaRecorder.stop();

                this.mediaRecorder = new MediaRecorder(this.mediaStream, {
                    mimeType: this.mimeType,
                });

                this.mediaRecorder.ondataavailable = (e) => {
                    if (e.data.size > 0) {
                        this.uploadChunk(e.data, this.chunkIndex);
                        this.chunkIndex++;
                    }
                };

                this.mediaRecorder.onstop = () => {
                    this.handleRecordingStop();
                };

                const initialBlob = new Blob(this.chunks, { type: this.mimeType });
                this.uploadChunk(initialBlob, 0);
                this.chunkIndex = 1;
                this.chunks = [];

                this.mediaRecorder.start(30000);
            }
        },

        pauseRecording() {
            if (this.mediaRecorder?.state === 'recording') {
                this.mediaRecorder.pause();
                this.state = 'paused';
                clearInterval(this.timerInterval);
            }
        },

        resumeRecording() {
            if (this.mediaRecorder?.state === 'paused') {
                this.mediaRecorder.resume();
                this.state = 'recording';

                this.timerInterval = setInterval(() => {
                    this.timer++;
                    if (this.timer === 300 && !this.isLongRecording) {
                        this.switchToChunkedMode();
                    }
                }, 1000);

                this.drawWaveform();
            }
        },

        async stopRecording() {
            if (!this.mediaRecorder || this.mediaRecorder.state === 'inactive') return;

            this.state = 'stopping';
            this.playBeep(500, 150);
            clearInterval(this.timerInterval);

            const stopTimeout = setTimeout(() => {
                if (this.state === 'stopping') {
                    console.warn('MediaRecorder onstop timeout - using fallback');
                    this.handleRecordingStop();
                }
            }, 3000);

            this.mediaRecorder._stopTimeout = stopTimeout;
            this.mediaRecorder.stop();
        },

        async handleRecordingStop() {
            if (this.mediaRecorder?._stopTimeout) {
                clearTimeout(this.mediaRecorder._stopTimeout);
            }

            if (this.state !== 'stopping') return;

            this.state = 'processing';

            if (this.isLongRecording) {
                await this.finalizeChunkedUpload();
            } else {
                this.recordedBlob = new Blob(this.chunks, { type: this.mimeType });

                if (this.recordedBlob.size === 0) {
                    this.state = 'error';
                    this.errorMessage = 'Recording produced no audio data. Please try again.';
                    return;
                }

                await this.saveToIndexedDB(this.recordedBlob);
                await this.uploadSingleFile(this.recordedBlob);
            }
        },

        // -- Upload Methods --
        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        async uploadChunk(blob, index) {
            const formData = new FormData();
            const ext = this.mimeType.includes('mp4') ? 'mp4' : 'webm';
            formData.append('chunk', blob, `chunk_${index}.${ext}`);
            formData.append('session_id', this.sessionId);
            formData.append('chunk_index', String(index));
            formData.append('mime_type', this.mimeType);

            try {
                const response = await fetch(this.chunkUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                if (response.ok) {
                    this.uploadedChunks++;
                }
            } catch (err) {
                console.error('Chunk upload failed:', err);
            }
        },

        async finalizeChunkedUpload() {
            this.state = 'uploading';
            this.uploadProgress = 90;

            try {
                const response = await fetch(this.finalizeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId,
                        mime_type: this.mimeType,
                        duration_seconds: this.timer,
                        language: 'en',
                    }),
                });

                if (response.ok) {
                    this.uploadProgress = 100;
                    this.state = 'complete';
                    this.successMessage = 'Recording uploaded. Transcription in progress...';
                    const data = await response.json();
                    this.$dispatch('recording-complete', { transcription: data.transcription });
                } else {
                    throw new Error('Finalize failed');
                }
            } catch (err) {
                this.state = 'error';
                this.errorMessage = 'Failed to finalize recording. Please try again.';
            }
        },

        async uploadSingleFile(blob) {
            this.state = 'uploading';
            this.uploadProgress = 0;

            const formData = new FormData();
            const ext = this.mimeType.includes('mp4') ? 'mp4' : 'webm';
            formData.append('audio', blob, `recording_${Date.now()}.${ext}`);
            formData.append('language', 'en');

            try {
                const xhr = new XMLHttpRequest();

                const uploadPromise = new Promise((resolve, reject) => {
                    xhr.upload.onprogress = (e) => {
                        if (e.lengthComputable) {
                            this.uploadProgress = Math.round((e.loaded / e.total) * 100);
                        }
                    };

                    xhr.onload = () => {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            resolve(JSON.parse(xhr.responseText));
                        } else {
                            reject(new Error(`Upload failed: ${xhr.status}`));
                        }
                    };

                    xhr.onerror = () => reject(new Error('Network error'));
                });

                xhr.open('POST', this.uploadUrl);
                xhr.setRequestHeader('X-CSRF-TOKEN', this.csrfToken());
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.send(formData);

                const data = await uploadPromise;
                this.uploadProgress = 100;
                this.state = 'complete';
                this.successMessage = 'Recording uploaded. Transcription in progress...';
                this.removeFromIndexedDB();
                this.$dispatch('recording-complete', { transcription: data.transcription });
            } catch (err) {
                await this.handleUploadError(err, blob);
            }
        },

        async handleUploadError(err, blob, retryCount = 0) {
            console.error('Upload error:', err);

            if (retryCount < 3) {
                const delays = [2000, 5000, 10000];
                this.errorMessage = `Upload failed. Retrying in ${delays[retryCount] / 1000}s...`;
                await new Promise(resolve => setTimeout(resolve, delays[retryCount]));
                this.errorMessage = '';

                return this.uploadSingleFile(blob);
            }

            this.state = 'error';
            this.errorMessage = 'Upload failed after multiple attempts. Your recording is saved locally — click Retry to try again.';
        },

        retryUpload() {
            if (this.recordedBlob) {
                this.uploadSingleFile(this.recordedBlob);
            } else if (this.isLongRecording) {
                this.finalizeChunkedUpload();
            } else {
                this.loadFromIndexedDB();
            }
        },

        // -- IndexedDB Recovery --
        getDB() {
            return new Promise((resolve, reject) => {
                const request = indexedDB.open('antaraflow-recordings', 1);
                request.onupgradeneeded = (e) => {
                    const db = e.target.result;
                    if (!db.objectStoreNames.contains('recordings')) {
                        db.createObjectStore('recordings', { keyPath: 'id' });
                    }
                };
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });
        },

        async saveToIndexedDB(blob) {
            try {
                const db = await this.getDB();
                const tx = db.transaction('recordings', 'readwrite');
                tx.objectStore('recordings').put({
                    id: `${this.meetingId}-${this.sessionId}`,
                    meetingId: this.meetingId,
                    blob: blob,
                    mimeType: this.mimeType,
                    duration: this.timer,
                    timestamp: new Date().toISOString(),
                });
            } catch (err) {
                console.error('IndexedDB save failed:', err);
            }
        },

        async removeFromIndexedDB() {
            try {
                const db = await this.getDB();
                const tx = db.transaction('recordings', 'readwrite');
                tx.objectStore('recordings').delete(`${this.meetingId}-${this.sessionId}`);
            } catch (err) {
                console.error('IndexedDB delete failed:', err);
            }
        },

        async checkPendingRecovery() {
            try {
                const db = await this.getDB();
                const tx = db.transaction('recordings', 'readonly');
                const store = tx.objectStore('recordings');
                const request = store.getAll();

                request.onsuccess = () => {
                    const recordings = request.result.filter(
                        (r) => r.meetingId === this.meetingId
                    );

                    if (recordings.length > 0) {
                        this.hasPendingRecovery = true;
                        this.recoveryTimestamp = recordings[0].timestamp;
                    }
                };
            } catch {
                // IndexedDB not available
            }
        },

        async recoverRecording() {
            try {
                const db = await this.getDB();
                const tx = db.transaction('recordings', 'readonly');
                const store = tx.objectStore('recordings');
                const request = store.getAll();

                request.onsuccess = () => {
                    const recordings = request.result.filter(
                        (r) => r.meetingId === this.meetingId
                    );

                    if (recordings.length > 0) {
                        const recording = recordings[0];
                        this.recordedBlob = recording.blob;
                        this.mimeType = recording.mimeType;
                        this.timer = recording.duration;
                        this.sessionId = recording.id.split('-').slice(1).join('-');
                        this.hasPendingRecovery = false;
                        this.uploadSingleFile(this.recordedBlob);
                    }
                };
            } catch (err) {
                this.errorMessage = 'Could not recover recording.';
            }
        },

        async discardRecovery() {
            try {
                const db = await this.getDB();
                const tx = db.transaction('recordings', 'readwrite');
                const store = tx.objectStore('recordings');
                const request = store.getAll();

                request.onsuccess = () => {
                    const recordings = request.result.filter(
                        (r) => r.meetingId === this.meetingId
                    );
                    const deleteTx = db.transaction('recordings', 'readwrite');
                    const deleteStore = deleteTx.objectStore('recordings');
                    recordings.forEach((r) => deleteStore.delete(r.id));
                };

                this.hasPendingRecovery = false;
            } catch {
                this.hasPendingRecovery = false;
            }
        },

        // -- Audio Feedback --
        playBeep(frequency, duration) {
            try {
                const ctx = new AudioContext();
                const oscillator = ctx.createOscillator();
                const gain = ctx.createGain();

                oscillator.connect(gain);
                gain.connect(ctx.destination);

                oscillator.frequency.value = frequency;
                gain.gain.value = 0.1;

                oscillator.start();
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration / 1000);
                oscillator.stop(ctx.currentTime + duration / 1000);
            } catch {
                // Audio feedback is optional
            }
        },

        // -- Reset & Cleanup --
        resetRecorder() {
            this.state = this.mediaStream ? 'ready' : 'idle';
            this.timer = 0;
            this.chunks = [];
            this.recordedBlob = null;
            this.errorMessage = '';
            this.successMessage = '';
            this.uploadProgress = 0;
            this.chunkIndex = 0;
            this.uploadedChunks = 0;
            this.isLongRecording = false;

            if (this.mediaStream) {
                this.drawWaveform();
            }
        },

        cleanup() {
            clearInterval(this.timerInterval);
            clearInterval(this.countdownInterval);
            clearInterval(this.chunkInterval);

            if (this.animationFrame) {
                cancelAnimationFrame(this.animationFrame);
            }

            if (this.mediaStream) {
                this.mediaStream.getTracks().forEach((track) => track.stop());
            }
        },
    };
}
