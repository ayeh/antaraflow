import { CLIPPING_DBFS, SILENCE_DBFS, rmsFromTimeDomain, toDbfs } from './audio/level.js';
import { createQuietWarning } from './audio/quiet-warning.js';
import { createTape } from './audio/tape-buffer.js';

/** Frames of level history the tape keeps: about sixty seconds at 60fps. */
const TAPE_CAPACITY = 3600;

/** Horizontal space one bar of the tape occupies, in canvas pixels. */
const BAR_SLOT_PX = 4;

/** Quietest level the meter draws. Below this a bar has no height. */
const METER_FLOOR_DBFS = -60;

/** Above this the signal is loud enough to be worth flagging as hot. */
const HOT_DBFS = -12;

/** Fast up, slow down — the asymmetry is what makes a meter readable. */
const ATTACK_MS = 30;
const RELEASE_MS = 200;

/** How long a peak stays pinned before it is allowed to fall, and how fast. */
const PEAK_HOLD_MS = 800;
const PEAK_FALL_DB_PER_SECOND = 40;

/** Teal while healthy, amber when hot, crimson when clipping. */
const BAND_COLOURS = ['#0D7377', '#D97706', '#DC2626'];

/** Colour of the flat line drawn whenever nothing is being captured. */
const IDLE_LINE_COLOUR = '#9CA3AF';

/**
 * Which colour band a level falls into. Module scope, not a closure inside the
 * draw loop: this is called a few hundred times per frame.
 */
function levelBand(dbfs) {
    if (dbfs > CLIPPING_DBFS) {
        return 2;
    }

    return dbfs > HOT_DBFS ? 1 : 0;
}

export default function audioRecorder(config) {
    return {
        // Config (passed from blade)
        config: config,
        uploadUrl: config.uploadUrl,
        chunkUrl: config.chunkUrl,
        finalizeUrl: config.finalizeUrl,
        cancelUrl: config.cancelUrl,
        meetingId: config.meetingId,

        // Live mode
        liveMode: config.liveMode || false,
        liveChunkUrl: config.liveChunkUrl || '',
        liveSessionId: config.liveSessionId || null,
        liveChunkNumber: config.initialChunkCount || 0,

        // State machine
        state: 'idle', // idle, requesting_permission, ready, countdown, recording, paused, stopping, processing, uploading, complete, error

        // Recording data
        mediaRecorder: null,
        mediaStream: null,
        captureStream: null,
        micOpen: false,
        permissionGranted: false,
        selectedDeviceId: null,
        inputDevices: [],
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
        audioContext: null,
        analyserNode: null,
        audioLevel: 0,
        showQuietWarning: false,

        // Beat of the status pill's dot. Flipped by the timer tick rather than
        // by a CSS animation so the dot and the digits change on the same
        // event; a pulse that drifts out of phase with the clock is exactly
        // what teaches people that motion on this screen means nothing.
        pulseOn: false,

        // Level meter internals. Every buffer here is allocated once and reused;
        // see drawWaveform() for why.
        _tape: createTape(TAPE_CAPACITY),
        _timeDomain: null,
        _barValues: null,
        _smoothedDbfs: SILENCE_DBFS,
        _peakDbfs: SILENCE_DBFS,
        _peakSetAtMs: 0,
        _lastFrameMs: 0,
        _quietWarning: null,

        // Chunk upload tracking
        chunkIndex: 0,
        isLongRecording: false,
        chunkInterval: null,
        uploadedChunks: 0,
        pendingChunkUploads: 0,

        // Language
        language: navigator.language ? navigator.language.split('-')[0] : 'en',

        // Recovery
        hasPendingRecovery: false,
        recoveryTimestamp: null,

        // Tab indicator. The originals are captured once and put back on the
        // way out, so a recorder that has finished leaves no trace in the tab.
        _originalTitle: '',
        _originalFaviconHref: null,
        _recordingFaviconHref: null,

        // Wall-clock tracking for accurate timer resync after background tab
        _recordingStartTime: null,
        _pausedDuration: 0,
        _pauseStartTime: null,

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
            this.selectedDeviceId = this.readStoredDeviceId();

            this._originalTitle = document.title;

            // Every state transition, without having to remember to call this
            // from each of the dozen places that assign to `state`.
            this.$watch('state', () => this.syncTabIndicator());

            this.$nextTick(() => {
                const canvas = this.$refs.waveformCanvas;
                if (canvas) {
                    this.canvasContext = canvas.getContext('2d');
                }
            });

            // Warn user before closing/refreshing tab while recording
            this._beforeUnloadHandler = (e) => {
                if (['recording', 'paused'].includes(this.state)) {
                    e.preventDefault();
                    e.returnValue = this.config.i18n?.recordingInProgress || 'Recording is in progress. Are you sure you want to leave?';
                    return e.returnValue;
                }
            };
            window.addEventListener('beforeunload', this._beforeUnloadHandler);

            // Resync timer using real wall-clock time when tab becomes visible again
            // (browsers throttle setInterval in background tabs)
            this._visibilityHandler = () => {
                if (document.visibilityState === 'visible' && this._recordingStartTime && ['recording', 'paused'].includes(this.state)) {
                    if (this.state === 'recording') {
                        this.timer = Math.floor((Date.now() - this._recordingStartTime - this._pausedDuration) / 1000);
                    }
                    // Restart waveform animation if it stopped
                    this.startWaveform();
                }
            };
            document.addEventListener('visibilitychange', this._visibilityHandler);
        },

        destroy() {
            this.cleanup();
            window.removeEventListener('beforeunload', this._beforeUnloadHandler);
            document.removeEventListener('visibilitychange', this._visibilityHandler);
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
                    // Strip codec parameters — server validation only accepts the base MIME type
                    this.mimeType = type.split(';')[0];
                    return;
                }
            }
            this.mimeType = 'audio/webm';
        },

        // -- Permission Handling --
        /**
         * Note whether permission was already granted, but do not open the microphone.
         *
         * Opening it here lights the browser's own recording indicator the moment the
         * page loads — long before the user presses record — which strips that
         * indicator of any meaning. The stream is opened at mic-check time instead,
         * and the existing countdown hides the ~200-500ms it costs to reopen.
         */
        async checkExistingPermission() {
            try {
                if (navigator.permissions?.query) {
                    const result = await navigator.permissions.query({ name: 'microphone' });
                    this.permissionGranted = result.state === 'granted';
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
                this.permissionGranted = true;
                this.state = 'ready';
            } catch (err) {
                this.handlePermissionError(err);
            }
        },

        handlePermissionError(err) {
            this.state = 'error';

            if (err.name === 'NotAllowedError') {
                this.errorMessage = this.config.i18n?.micDenied || 'Microphone access denied. Please allow microphone access in your browser settings.';
            } else if (err.name === 'NotFoundError') {
                this.errorMessage = this.config.i18n?.micNotFound || 'No microphone found. Please connect a microphone and try again.';
            } else if (err.name === 'NotReadableError') {
                this.errorMessage = this.config.i18n?.micInUse || 'Microphone is in use by another application. Please close it and try again.';
            } else {
                this.errorMessage = this.config.i18n?.micError || 'Could not access microphone. Please try again.';
            }
        },

        // -- Stream Setup --
        /**
         * Open the microphone with the browser's call-tuned processing disabled.
         *
         * echoCancellation, noiseSuppression and autoGainControl are designed for one
         * person close to a mic on a video call. In a meeting room they actively
         * destroy the signal we need: noise suppression gates distant speech out as
         * noise, echo cancellation suppresses the room reflections that carry a far
         * speaker's energy, and auto gain pumps between loud and quiet talkers.
         * Disabling echo cancellation also stops Chrome forcing its narrow processing
         * path, so we receive full-band device audio.
         */
        async setupStream() {
            this.mediaStream = await this.openMicrophone(this.selectedDeviceId);

            this.captureStream = this.buildGainChain(this.mediaStream) ?? this.mediaStream;
            this.micOpen = true;

            await this.loadInputDevices();

            this.startWaveform();
        },

        /**
         * The capture constraints, built fresh so no caller can mutate a shared
         * object. See setupStream() for why the browser's own processing is off.
         *
         * @return {{echoCancellation: boolean, noiseSuppression: boolean, autoGainControl: boolean, voiceIsolation: boolean, channelCount: number}}
         */
        captureConstraints() {
            return {
                echoCancellation: false,
                noiseSuppression: false,
                autoGainControl: false,
                voiceIsolation: false,
                channelCount: 1,
            };
        },

        /**
         * Open the microphone, preferring the device the user last chose.
         *
         * The remembered device id outlives the device: unplug the USB conference
         * mic between meetings and `deviceId: {exact: ...}` rejects with
         * OverconstrainedError (some browsers report NotFoundError instead). Left
         * alone that leaves the user unable to record at all, behind an error
         * naming a microphone they are not trying to use. Forget the stale choice
         * and fall back to the system default — recording matters more than the
         * preference. Every other failure, permission denial above all, belongs to
         * the caller to report.
         */
        async openMicrophone(deviceId) {
            const audio = this.captureConstraints();

            if (!deviceId) {
                return navigator.mediaDevices.getUserMedia({ audio });
            }

            try {
                return await navigator.mediaDevices.getUserMedia({
                    audio: { ...audio, deviceId: { exact: deviceId } },
                });
            } catch (err) {
                if (err.name !== 'OverconstrainedError' && err.name !== 'NotFoundError') {
                    throw err;
                }

                this.forgetSelectedDevice();

                return navigator.mediaDevices.getUserMedia({ audio });
            }
        },

        // -- Input Device Selection --
        /**
         * List available inputs. Device labels are only populated after permission
         * has been granted, so this runs after setupStream(), never before.
         */
        async loadInputDevices() {
            try {
                const devices = await navigator.mediaDevices.enumerateDevices();
                this.inputDevices = devices.filter((d) => d.kind === 'audioinput');
            } catch {
                this.inputDevices = [];
            }
        },

        /**
         * Switching device means closing the microphone and opening another one.
         *
         * Mid-recording that hands MediaRecorder a dead track and ends the capture
         * silently, so a meeting could be lost to a stray click on a dropdown. The
         * picker is disabled in the UI while a recording is in flight; this guard
         * is the one that actually holds, because the markup can be bypassed and
         * the state can change between the click and the handler.
         */
        async selectInputDevice(deviceId) {
            if (this.isMicrophoneBusy()) {
                return;
            }

            this.selectedDeviceId = deviceId;
            this.storeDeviceId(deviceId);

            if (!this.micOpen) {
                return;
            }

            this.releaseStream();

            try {
                await this.setupStream();
                this.state = 'ready';
            } catch (err) {
                this.handlePermissionError(err);
            }
        },

        /**
         * Whether the microphone is committed to a recording that a device swap
         * would destroy. 'stopping' counts: the encoder is still flushing.
         */
        isMicrophoneBusy() {
            return ['countdown', 'recording', 'paused', 'stopping'].includes(this.state);
        },

        readStoredDeviceId() {
            try {
                return localStorage.getItem('antaranote-mic-device');
            } catch {
                return null;
            }
        },

        storeDeviceId(deviceId) {
            try {
                localStorage.setItem('antaranote-mic-device', deviceId);
            } catch {
                // A remembered device is a convenience, not a requirement.
            }
        },

        forgetSelectedDevice() {
            this.selectedDeviceId = null;

            try {
                localStorage.removeItem('antaranote-mic-device');
            } catch {
                // Nothing to forget when storage is unavailable.
            }
        },

        /**
         * Close the microphone and tear the audio graph down.
         *
         * Only stopping the tracks of the raw getUserMedia stream releases the
         * hardware and puts the browser's recording indicator out. The capture
         * stream's tracks belong to the Web Audio destination node and hold no
         * device, but they keep the graph alive and stay "live" for anything still
         * holding the stream, so stop them too. When the gain chain could not be
         * built the two are the same object.
         */
        releaseStream() {
            [this.captureStream, this.mediaStream].forEach((stream) => {
                stream?.getTracks().forEach((track) => track.stop());
            });

            if (this.audioContext && this.audioContext.state !== 'closed') {
                this.audioContext.close().catch(() => {});
            }

            // Null the streams so the next start re-opens the microphone instead of
            // handing MediaRecorder a set of dead tracks.
            this.mediaStream = null;
            this.captureStream = null;
            this.audioContext = null;
            this.analyserNode = null;
            this.micOpen = false;
        },

        /**
         * Raise the signal before it reaches the Opus encoder.
         *
         * MediaRecorder encodes at a low bitrate. A distant speaker sitting at -50
         * dBFS receives almost no bits, and no amount of server-side normalisation
         * recovers that — it only amplifies encoder noise. Gain has to be applied
         * before encoding, which means here.
         *
         * Returns null when Web Audio is unavailable, in which case the caller falls
         * back to the raw stream. A recording must never be silent because of this.
         */
        buildGainChain(stream) {
            try {
                this.audioContext = new (window.AudioContext || window.webkitAudioContext)();

                const source = this.audioContext.createMediaStreamSource(stream);

                const highpass = this.audioContext.createBiquadFilter();
                highpass.type = 'highpass';
                highpass.frequency.value = 80;

                const compressor = this.audioContext.createDynamicsCompressor();
                compressor.threshold.value = -35;
                compressor.ratio.value = 4;
                compressor.attack.value = 0.02;
                compressor.release.value = 0.25;

                const gain = this.audioContext.createGain();
                gain.gain.value = 1.5;

                this.analyserNode = this.audioContext.createAnalyser();
                this.analyserNode.fftSize = 2048;

                const destination = this.audioContext.createMediaStreamDestination();

                // A destination node defaults to two channels and up-mixes our mono
                // capture into both, so the encoder would spend half its bitrate on a
                // duplicate. Keeping it mono leaves those bits for the quiet speaker.
                try {
                    destination.channelCount = 1;
                } catch {
                    // Stereo output is wasteful but harmless; keep the chain.
                }

                source.connect(highpass);
                highpass.connect(compressor);
                compressor.connect(gain);
                gain.connect(this.analyserNode);
                this.analyserNode.connect(destination);

                this.resumeAudioContext();

                return destination.stream;
            } catch {
                if (this.audioContext) {
                    this.audioContext.close?.().catch(() => {});
                }
                this.audioContext = null;
                this.analyserNode = null;

                return null;
            }
        },

        /**
         * An AudioContext constructed without user activation starts suspended, and a
         * suspended context renders silence into the destination stream. Because the
         * capture stream now flows through the graph, that would produce an entirely
         * silent recording rather than merely a frozen level meter.
         */
        resumeAudioContext() {
            if (this.audioContext?.state === 'suspended') {
                this.audioContext.resume().catch(() => {});
            }
        },

        // -- Waveform Visualization (time-based animation) --
        /**
         * Enter the animation loop, or do nothing if it is already running.
         *
         * Several paths want the meter alive — opening the stream, starting,
         * resuming, returning to a backgrounded tab — and calling drawWaveform()
         * directly from each of them started an independent requestAnimationFrame
         * loop every time, so a resumed recording painted the same canvas two or
         * three times per frame.
         */
        startWaveform() {
            if (this.animationFrame) {
                return;
            }

            this.drawWaveform();
        },

        /**
         * Paint one frame of the level meter.
         *
         * The meter used to animate layered sine waves in `ready`, `paused` and
         * `recording` alike, so the most eye-catching thing on the screen moved
         * whether or not a single sample was being captured. Motion here now
         * means exactly one thing: audio is going into the recording. Every other
         * state gets a flat line.
         *
         * This runs sixty times a second for as long as a meeting lasts, so it
         * allocates nothing: the analyser buffer and the bar values are allocated
         * once and written over in place, and the tape itself is a fixed-capacity
         * ring.
         */
        drawWaveform() {
            // The pending frame has now run; a null handle is what lets
            // startWaveform() tell a stopped loop from a running one.
            this.animationFrame = null;

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

            this.paintMeterBackground(ctx, width, height);

            // Without an analyser there is no level to show. That happens only
            // when Web Audio could not be built, in which case the recording is
            // still running on the raw stream and a flat line is the honest
            // answer — an invented one would be worse.
            if (this.state === 'recording' && this.analyserNode) {
                this.sampleLevel(performance.now());
                this.paintTape(ctx, width, height);
            } else {
                this.audioLevel = 0;
                this.paintFlatLine(ctx, width, height);
            }

            if (!['idle', 'complete', 'error'].includes(this.state)) {
                this.animationFrame = requestAnimationFrame(() => this.drawWaveform());
            }
        },

        paintMeterBackground(ctx, width, height) {
            ctx.fillStyle = getComputedStyle(document.documentElement)
                .getPropertyValue('--color-gray-50')?.trim() || '#f9fafb';
            if (document.documentElement.classList.contains('dark')) {
                ctx.fillStyle = 'rgba(55, 65, 81, 0.3)';
            }
            ctx.fillRect(0, 0, width, height);
        },

        /**
         * A single flat line, drawn whenever nothing is being captured.
         */
        paintFlatLine(ctx, width, height) {
            ctx.fillStyle = IDLE_LINE_COLOUR;
            ctx.fillRect(0, (height / 2) - 1, width, 2);
        },

        /**
         * Measure the current level, smooth it, and add it to the tape.
         *
         * Smoothing is a time constant rather than a fixed fraction, so the meter
         * behaves the same on a 120Hz display, a throttled background tab, or a
         * phone that has dropped to 30fps.
         */
        sampleLevel(nowMs) {
            const levelDbfs = this.readLevelDbfs();

            // A tab that was backgrounded can return with an enormous gap; clamp
            // it so the meter eases in rather than snapping.
            const elapsedMs = this._lastFrameMs ? Math.min(nowMs - this._lastFrameMs, 250) : 0;
            const firstFrame = this._lastFrameMs === 0;
            this._lastFrameMs = nowMs;

            if (firstFrame) {
                // Start the filter at what is actually there. Easing up from the
                // silence floor instead paints a bar or two of level that was
                // never captured, at the very moment the user is watching to see
                // whether recording has started.
                this._smoothedDbfs = levelDbfs;
            } else {
                const timeConstant = levelDbfs > this._smoothedDbfs ? ATTACK_MS : RELEASE_MS;
                this._smoothedDbfs += (levelDbfs - this._smoothedDbfs) * (1 - Math.exp(-elapsedMs / timeConstant));
            }

            this._tape.push(this._smoothedDbfs);
            this.audioLevel = this.normaliseLevel(this._smoothedDbfs);

            if (this._smoothedDbfs >= this._peakDbfs) {
                this._peakDbfs = this._smoothedDbfs;
                this._peakSetAtMs = nowMs;
            } else if (nowMs - this._peakSetAtMs > PEAK_HOLD_MS) {
                this._peakDbfs = Math.max(
                    this._smoothedDbfs,
                    this._peakDbfs - (PEAK_FALL_DB_PER_SECOND * elapsedMs) / 1000,
                );
            }

            // The raw measurement, not the smoothed one: the warning is about what
            // is reaching the encoder, not about what looks good on screen.
            if (this._quietWarning?.observe(levelDbfs, nowMs)) {
                this.showQuietWarning = true;
            }
        },

        /**
         * Current level in dBFS, read into a buffer that is allocated once.
         */
        readLevelDbfs() {
            const size = this.analyserNode.fftSize;

            if (!this._timeDomain || this._timeDomain.length !== size) {
                this._timeDomain = new Uint8Array(size);
            }

            this.analyserNode.getByteTimeDomainData(this._timeDomain);

            return toDbfs(rmsFromTimeDomain(this._timeDomain));
        },

        /**
         * Map a dBFS reading onto 0..1 of the meter's height.
         */
        normaliseLevel(dbfs) {
            return Math.min(1, Math.max(0, (dbfs - METER_FLOOR_DBFS) / -METER_FLOOR_DBFS));
        },

        /**
         * Draw the tape, oldest sample at the left edge and newest at the right,
         * so a bar enters on the right every frame and history slides away left.
         *
         * The tape keeps about a minute; the meter shows as much of its newest
         * end as the canvas has bars for. Collapsing the whole minute onto a
         * 600px canvas was tried and rejected: it advances two bars a second and
         * reads as a still image, which is the problem this meter exists to fix.
         */
        paintTape(ctx, width, height) {
            const barCount = Math.max(1, Math.floor(width / BAR_SLOT_PX));

            if (!this._barValues || this._barValues.length !== barCount) {
                this._barValues = new Float32Array(barCount);
            }

            this._tape.readInto(this._barValues, SILENCE_DBFS);

            const centerY = height / 2;
            const maxHalf = (height / 2) - 4;
            const barWidth = Math.max(2, BAR_SLOT_PX - 1);
            const slot = width / barCount;

            // One path per colour band rather than one per bar: setting fillStyle
            // is the expensive part of this loop.
            for (let band = 0; band < BAND_COLOURS.length; band++) {
                ctx.fillStyle = BAND_COLOURS[band];
                ctx.beginPath();

                for (let bar = 0; bar < barCount; bar++) {
                    const dbfs = this._barValues[bar];

                    if (dbfs <= METER_FLOOR_DBFS || levelBand(dbfs) !== band) {
                        continue;
                    }

                    const half = Math.max(1, this.normaliseLevel(dbfs) * maxHalf);
                    ctx.roundRect(bar * slot, centerY - half, barWidth, half * 2, Math.min(barWidth / 2, half));
                }

                ctx.fill();
            }

            this.paintPeakMarker(ctx, width, centerY, maxHalf);
        },

        /**
         * The peak marker sits at the newest edge and holds its height briefly,
         * so a transient that is gone in two frames is still readable.
         */
        paintPeakMarker(ctx, width, centerY, maxHalf) {
            if (this._peakDbfs <= METER_FLOOR_DBFS) {
                return;
            }

            const half = Math.max(1, this.normaliseLevel(this._peakDbfs) * maxHalf);
            const markerWidth = BAR_SLOT_PX * 3;

            ctx.fillStyle = BAND_COLOURS[levelBand(this._peakDbfs)];
            ctx.fillRect(width - markerWidth, centerY - half - 1, markerWidth, 2);
            ctx.fillRect(width - markerWidth, centerY + half - 1, markerWidth, 2);
        },

        /**
         * Forget everything the meter is holding, so a new recording starts from
         * silence instead of inheriting the last one's tail.
         */
        resetMeter() {
            this._tape.clear();
            this._smoothedDbfs = SILENCE_DBFS;
            this._peakDbfs = SILENCE_DBFS;
            this._peakSetAtMs = 0;
            this._lastFrameMs = 0;
            this.audioLevel = 0;
        },

        // -- Tab Indicator --
        /**
         * Mirror recording state into the tab title and favicon, so the signal
         * survives the user switching to another tab entirely.
         */
        syncTabIndicator() {
            if (this.state === 'recording') {
                document.title = `● ${this.formattedTimer} — ${this.config.i18n?.recording || 'RECORDING'}`;
                this.setFavicon(true);

                return;
            }

            document.title = this._originalTitle;
            this.setFavicon(false);
        },

        /**
         * Swap the tab icon for a red disc while recording, and put the real
         * one back afterwards.
         *
         * The disc is drawn on a canvas rather than composited onto the app's
         * own favicon: that favicon is tenant-brandable and may be an
         * arbitrary — possibly cross-origin — URL, which would taint the canvas
         * and make toDataURL() throw, and an .ico is not reliably decodable
         * into one either. Drawing it also avoids shipping an asset and the
         * network request to fetch it. At 16px a badged logo is illegible
         * anyway, where a solid red disc is not, and it speaks the same
         * language as the pill's ● and the browser's own indicator.
         */
        setFavicon(recording) {
            const link = document.querySelector('link[rel="icon"]');

            if (!link) {
                return;
            }

            if (this._originalFaviconHref === null) {
                this._originalFaviconHref = link.getAttribute('href') ?? '';
            }

            if (!recording) {
                link.setAttribute('href', this._originalFaviconHref);

                return;
            }

            const dot = this.recordingFaviconHref();

            if (dot) {
                link.setAttribute('href', dot);
            }
        },

        /**
         * The red disc as a data URI, drawn once and reused. Returns null when
         * canvas is unavailable, in which case the tab title carries the signal
         * on its own.
         */
        recordingFaviconHref() {
            if (this._recordingFaviconHref !== null) {
                return this._recordingFaviconHref;
            }

            try {
                const canvas = document.createElement('canvas');
                canvas.width = 32;
                canvas.height = 32;

                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#DC2626';
                ctx.beginPath();
                ctx.arc(16, 16, 14, 0, Math.PI * 2);
                ctx.fill();

                this._recordingFaviconHref = canvas.toDataURL('image/png');
            } catch {
                // Remember the failure as an empty string rather than null, so
                // this is not retried on every tick.
                this._recordingFaviconHref = '';
            }

            return this._recordingFaviconHref;
        },

        /**
         * Hand the tab back exactly as it was found.
         */
        restoreTabIndicator() {
            if (this._originalTitle) {
                document.title = this._originalTitle;
            }

            this.setFavicon(false);
        },

        // -- Recording Controls --
        async startRecording() {
            // Keyed off the stream rather than the state name: the microphone is now
            // opened on demand, so 'idle' no longer implies "never had permission"
            // and 'ready' no longer implies "stream is open". The stream itself is
            // the only honest answer to whether we still need to open one.
            if (!this.captureStream) {
                await this.requestPermission();
                if (this.state !== 'ready') return;
            }

            this.resumeAudioContext();

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

            // A fresh warning per recording: it fires once, and the next meeting
            // deserves to be told too.
            this._quietWarning = createQuietWarning();
            this.showQuietWarning = false;
            this.resetMeter();

            this.mediaRecorder = new MediaRecorder(this.captureStream, {
                mimeType: this.mimeType,
            });

            this.mediaRecorder.ondataavailable = (e) => {
                if (e.data.size > 0) {
                    this.chunks.push(e.data);

                    if (this.isLongRecording) {
                        if (this.liveMode) {
                            this.uploadLiveChunk(e.data);
                        } else {
                            this.uploadChunk(e.data, this.chunkIndex);
                        }
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
                this.errorMessage = this.config.i18n?.recordingFailed || 'Recording failed. Please try again.';
                this.cleanup();
            };

            this.mediaRecorder.start();
            this.state = 'recording';
            this.playBeep(300, 200);

            this._recordingStartTime = Date.now();
            this._pausedDuration = 0;
            this._pauseStartTime = null;

            this.pulseOn = true;
            this.startTimerTick();

            // In live mode, switch to chunked mode immediately with 30-second intervals
            if (this.liveMode) {
                this.$nextTick(() => {
                    this.switchToChunkedMode();
                });
            }

            this.startWaveform();
        },

        switchToChunkedMode() {
            this.isLongRecording = true;

            if (this.mediaRecorder?.state === 'recording') {
                this.mediaRecorder.stop();

                // Upload the initial recording as chunk 0
                const initialBlob = new Blob(this.chunks, { type: this.mimeType });
                if (initialBlob.size > 0) {
                    if (this.liveMode) {
                        this.uploadLiveChunk(initialBlob);
                    } else {
                        this.uploadChunk(initialBlob, 0);
                    }
                }
                this.chunkIndex = 1;
                this.chunks = [];

                // Start a fresh recorder for each chunk (stop/restart cycle)
                // This ensures every chunk has a valid file header
                this.startChunkCycle();
            }
        },

        startChunkCycle() {
            const startNewChunk = () => {
                if (this.state !== 'recording' || !this.captureStream) return;

                const recorder = new MediaRecorder(this.captureStream, {
                    mimeType: this.mimeType,
                });
                this.mediaRecorder = recorder;

                const chunkData = [];
                recorder.ondataavailable = (e) => {
                    if (e.data.size > 0) {
                        chunkData.push(e.data);
                    }
                };

                recorder.onstop = () => {
                    // If user stopped recording, delegate to handleRecordingStop
                    if (this.state === 'stopping') {
                        this.handleRecordingStop();
                        return;
                    }

                    // Upload the complete chunk (has proper file header)
                    const blob = new Blob(chunkData, { type: this.mimeType });
                    if (blob.size > 0) {
                        if (this.liveMode) {
                            this.uploadLiveChunk(blob);
                        } else {
                            this.uploadChunk(blob, this.chunkIndex);
                        }
                        this.chunkIndex++;
                    }

                    // Start the next chunk immediately
                    startNewChunk();
                };

                recorder.onerror = () => {
                    // Try to restart on error
                    startNewChunk();
                };

                recorder.start();

                // Stop after 30 seconds to flush as a complete file
                this.chunkInterval = setTimeout(() => {
                    if (recorder.state === 'recording') {
                        recorder.stop();
                    }
                }, 30000);
            };

            startNewChunk();
        },

        /**
         * The one-second heartbeat behind the timer and the status pill.
         *
         * Starting and resuming a recording share it so the two can never fall
         * out of step, and so there is a single place that owns what happens
         * once a second.
         */
        startTimerTick() {
            clearInterval(this.timerInterval);

            this.timerInterval = setInterval(() => {
                this.timer++;
                this.pulseOn = !this.pulseOn;
                this.syncTabIndicator();

                if (this.timer === 300 && !this.isLongRecording && !this.liveMode) {
                    this.switchToChunkedMode();
                }
            }, 1000);
        },

        pauseRecording() {
            if (this.mediaRecorder?.state === 'recording') {
                this.mediaRecorder.pause();
                this.state = 'paused';
                this._pauseStartTime = Date.now();
                clearInterval(this.timerInterval);

                // Nothing is being captured and the clock has stopped, so the
                // dot stops beating too and sits steady.
                this.pulseOn = false;
            }
        },

        resumeRecording() {
            if (this.mediaRecorder?.state === 'paused') {
                this.mediaRecorder.resume();
                this.state = 'recording';

                if (this._pauseStartTime) {
                    this._pausedDuration += Date.now() - this._pauseStartTime;
                    this._pauseStartTime = null;
                }

                this.pulseOn = true;
                this.startTimerTick();

                this.startWaveform();
            }
        },

        async stopRecording() {
            if (!this.mediaRecorder || this.mediaRecorder.state === 'inactive') return;

            this.state = 'stopping';
            this.playBeep(500, 150);
            clearInterval(this.timerInterval);
            clearTimeout(this.chunkInterval);

            const stopTimeout = setTimeout(() => {
                if (this.state === 'stopping') {
                    console.warn('MediaRecorder onstop timeout - using fallback');
                    this.handleRecordingStop();
                }
            }, 3000);

            this._stopTimeout = stopTimeout;
            this.mediaRecorder.stop();
        },

        async handleRecordingStop() {
            if (this._stopTimeout) {
                clearTimeout(this._stopTimeout);
            }

            if (this.state !== 'stopping') return;

            this.state = 'processing';

            // Recording is over on every path from here, so let the microphone go
            // now rather than after the upload settles. The browser's indicator
            // then goes out exactly when recording stops, including when the
            // upload fails and the user sits on the retry button.
            this.releaseStream();

            // In live mode, chunks are already uploaded via the live endpoint
            if (this.liveMode && this.isLongRecording) {
                this.state = 'complete';
                this.successMessage = this.config.i18n?.recordingStopped || 'Recording stopped. Audio chunks have been sent for transcription.';
                return;
            }

            if (this.isLongRecording) {
                await this.finalizeChunkedUpload();
            } else {
                this.recordedBlob = new Blob(this.chunks, { type: this.mimeType });

                if (this.recordedBlob.size === 0) {
                    this.state = 'error';
                    this.errorMessage = this.config.i18n?.noAudioData || 'Recording produced no audio data. Please try again.';
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

            this.pendingChunkUploads++;
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
            } finally {
                this.pendingChunkUploads--;
            }
        },

        async uploadLiveChunk(blob) {
            if (blob.size < 1024) return;

            const chunkNumber = this.liveChunkNumber;
            const chunkDuration = 30;
            const startTime = chunkNumber * chunkDuration;
            const endTime = startTime + chunkDuration;

            this.liveChunkNumber++;

            const formData = new FormData();
            const ext = this.mimeType.includes('mp4') ? 'mp4' : 'webm';
            formData.append('audio', blob, `live_chunk_${chunkNumber}.${ext}`);
            formData.append('chunk_number', String(chunkNumber));
            formData.append('start_time', String(startTime));
            formData.append('end_time', String(endTime));

            try {
                const response = await fetch(this.liveChunkUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                if (response.ok) {
                    this.uploadedChunks++;
                } else {
                    console.error('Live chunk upload returned', response.status, await response.text().catch(() => ''));
                }
            } catch (err) {
                console.error('Live chunk upload failed:', err);
            }
        },

        async finalizeChunkedUpload() {
            this.state = 'uploading';
            this.uploadProgress = 90;

            // Wait for any in-flight chunk uploads to settle before finalizing
            let waited = 0;
            while (this.pendingChunkUploads > 0 && waited < 15000) {
                await new Promise((resolve) => setTimeout(resolve, 200));
                waited += 200;
            }

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
                        duration_seconds: Math.max(1, this.timer),
                        language: this.language,
                    }),
                });

                if (response.ok) {
                    this.uploadProgress = 100;
                    this.state = 'complete';
                    this.successMessage = this.config.i18n?.recordingUploaded || 'Recording uploaded. Transcription in progress...';
                    const data = await response.json();
                    this.$dispatch('recording-complete', { transcription: data.transcription });
                } else {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(data.message || 'Finalize failed');
                }
            } catch (err) {
                this.state = 'error';
                this.errorMessage = err.message && err.message !== 'Finalize failed'
                    ? err.message
                    : (this.config.i18n?.finalizeFailed || 'Failed to finalize recording. Please try again.');
            }
        },

        async uploadSingleFile(blob) {
            this.state = 'uploading';
            this.uploadProgress = 0;

            const formData = new FormData();
            const ext = this.mimeType.includes('mp4') ? 'mp4' : 'webm';
            formData.append('audio', blob, `recording_${Date.now()}.${ext}`);
            formData.append('language', this.language);

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
                this.successMessage = this.config.i18n?.recordingUploaded || 'Recording uploaded. Transcription in progress...';
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
                this.errorMessage = (this.config.i18n?.uploadRetrying || 'Upload failed. Retrying in {seconds}s...').replace('{seconds}', delays[retryCount] / 1000);
                await new Promise(resolve => setTimeout(resolve, delays[retryCount]));
                this.errorMessage = '';

                return this.uploadSingleFile(blob);
            }

            this.state = 'error';
            this.errorMessage = this.config.i18n?.uploadFailed || 'Upload failed after multiple attempts. Your recording is saved locally — click Retry to try again.';
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
                const request = indexedDB.open('antaranote-recordings', 1);
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
                this.errorMessage = this.config.i18n?.recoverFailed || 'Could not recover recording.';
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
                oscillator.onended = () => ctx.close();
            } catch {
                // Audio feedback is optional
            }
        },

        // -- Reset & Cleanup --
        resetRecorder() {
            this.releaseStream();
            this.state = 'idle';
            this.timer = 0;
            this.chunks = [];
            this.recordedBlob = null;
            this.errorMessage = '';
            this.successMessage = '';
            this.uploadProgress = 0;
            this.chunkIndex = 0;
            this.uploadedChunks = 0;
            this.pendingChunkUploads = 0;
            this.isLongRecording = false;
            this.pulseOn = false;

            this._quietWarning?.reset();
            this.showQuietWarning = false;
            this.resetMeter();

            // The waveform canvas is hidden while idle, so there is nothing to paint.
        },

        cleanup() {
            clearInterval(this.timerInterval);
            clearInterval(this.countdownInterval);
            clearTimeout(this.chunkInterval);

            if (this.animationFrame) {
                cancelAnimationFrame(this.animationFrame);
                this.animationFrame = null;
            }

            this.restoreTabIndicator();
            this.releaseStream();
        },
    };
}
