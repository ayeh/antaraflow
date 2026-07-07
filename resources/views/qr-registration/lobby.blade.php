<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $meeting->title }} · Live Lobby</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('lobbyPage', (config) => ({
                qrData: config.qrData,
                qrUrl: config.registerUrl,
                attendeesUrl: config.attendeesUrl,
                lobbyTitle: config.title,
                lobbyOrgName: config.orgName,
                lobbyOrgLogo: config.orgLogo,

                lobbyAttendees: [],
                lobbyCount: 0,
                lobbyMax: config.qrData?.max_attendees || null,
                lobbyLoaded: false,
                lobbyEnded: false,
                lobbyHero: null,
                lobbyBanner: null,
                lobbyNewIds: [],
                countPulse: false,

                lobbySoundOn: false,
                lobbyAudioCtx: null,
                lobbyLangs: ['Welcome', 'Selamat Datang', '欢迎', 'नमस्ते', 'مرحبا', 'ようこそ', 'Bienvenue'],
                lobbyLangIndex: 0,

                lobbyAppName: config.appName,
                lobbyTaglines: @js([
                    __('Every meeting, minuted by AI.'),
                    __('Turn conversations into decisions — automatically.'),
                    __('This live check-in is just one small feature.'),
                    __('Smart minutes & action items, handled for you.'),
                    __('Meetings that write their own notes.'),
                    __('From discussion to decisions in seconds.'),
                    __("There's a smarter way to run meetings."),
                    __('Curious what else it can do?'),
                ]),
                lobbyTagline: '',
                lobbyTaglineTimer: null,

                isFullscreen: false,
                lobbyPollId: null,
                lobbyLangTimer: null,
                lobbyHeroTimer: null,
                lobbyBannerTimer: null,

                init() {
                    this.fetchLobby();
                    this.lobbyPollId = setInterval(() => this.fetchLobby(), 3000);
                    this.lobbyLangTimer = setInterval(() => {
                        this.lobbyLangIndex = (this.lobbyLangIndex + 1) % this.lobbyLangs.length;
                    }, 2200);
                    this.lobbyTagline = this.pickTagline();
                    this.lobbyTaglineTimer = setInterval(() => { this.lobbyTagline = this.pickTagline(); }, 5500);
                    document.addEventListener('fullscreenchange', () => {
                        this.isFullscreen = !!document.fullscreenElement;
                    });
                },

                pickTagline() {
                    const list = this.lobbyTaglines;
                    if (!list.length) { return ''; }
                    if (list.length === 1) { return list[0]; }
                    let next = this.lobbyTagline;
                    while (next === this.lobbyTagline) {
                        next = list[Math.floor(Math.random() * list.length)];
                    }
                    return next;
                },

                get lobbyProgress() {
                    if (!this.lobbyMax) { return null; }
                    return Math.min(100, Math.round((this.lobbyCount / this.lobbyMax) * 100));
                },

                get lobbyRingOffset() {
                    const circumference = 339.292;
                    return circumference - (circumference * (this.lobbyProgress ?? 0) / 100);
                },

                get lobbyWelcomeWord() {
                    return this.lobbyLangs[this.lobbyLangIndex];
                },

                async fetchLobby() {
                    try {
                        const response = await fetch(this.attendeesUrl, { headers: { 'Accept': 'application/json' } });
                        if (!response.ok) return;
                        const data = await response.json();
                        const incoming = data.attendees || [];
                        const existingIds = new Set(this.lobbyAttendees.map(a => a.id));
                        const fresh = incoming.filter(a => !existingIds.has(a.id));
                        const prevCount = this.lobbyCount;

                        this.lobbyAttendees = incoming.slice().reverse();
                        this.lobbyMax = data.max_attendees || null;
                        const newCount = data.registrations_count ?? incoming.length;

                        if (this.lobbyLoaded && fresh.length > 0) {
                            this.fireConfetti(36);
                            this.playChime();
                            this.countPulse = true;
                            setTimeout(() => { this.countPulse = false; }, 700);
                            this.showHero(this.lobbyAttendees[0], fresh.length - 1);
                            this.lobbyNewIds = fresh.map(a => a.id);
                            setTimeout(() => { this.lobbyNewIds = []; }, 5000);
                        }

                        if (this.lobbyLoaded) {
                            this.checkMilestones(newCount, prevCount);
                        }

                        this.lobbyCount = newCount;
                        this.lobbyLoaded = true;

                        if (data.is_active === false && !this.lobbyEnded) {
                            this.lobbyEnded = true;
                            this.celebrate('{{ __("Registration closed") }}');
                        }
                    } catch (e) {
                        console.error('Lobby poll failed:', e);
                    }
                },

                showHero(att, extra) {
                    if (!att) return;
                    this.lobbyHero = { name: att.name, company: att.company || null, extra: extra > 0 ? extra : 0 };
                    if (this.lobbyHeroTimer) { clearTimeout(this.lobbyHeroTimer); }
                    this.lobbyHeroTimer = setTimeout(() => { this.lobbyHero = null; }, 2800);
                },

                checkMilestones(count, prev) {
                    if (!this.lobbyMax || count <= prev) return;
                    const half = Math.ceil(this.lobbyMax / 2);
                    if (this.lobbyMax >= 4 && count >= half && prev < half && count < this.lobbyMax) {
                        this.celebrate('{{ __("Halfway there!") }}');
                    }
                    if (count >= this.lobbyMax && prev < this.lobbyMax) {
                        this.celebrate('{{ __("We\'re full! 🎉") }}');
                    }
                },

                celebrate(message) {
                    this.lobbyBanner = message;
                    this.fireConfetti(140);
                    if (this.lobbyBannerTimer) { clearTimeout(this.lobbyBannerTimer); }
                    this.lobbyBannerTimer = setTimeout(() => { this.lobbyBanner = null; }, 3600);
                },

                initLobbyAudio() {
                    try {
                        if (!this.lobbyAudioCtx) {
                            const Ctx = window.AudioContext || window.webkitAudioContext;
                            if (Ctx) { this.lobbyAudioCtx = new Ctx(); }
                        }
                        if (this.lobbyAudioCtx && this.lobbyAudioCtx.state === 'suspended') {
                            this.lobbyAudioCtx.resume();
                        }
                    } catch (e) { /* audio unsupported */ }
                },

                toggleLobbySound() {
                    this.lobbySoundOn = !this.lobbySoundOn;
                    if (this.lobbySoundOn) { this.initLobbyAudio(); this.playChime(); }
                },

                playChime() {
                    if (!this.lobbySoundOn || !this.lobbyAudioCtx) return;
                    try {
                        const ctx = this.lobbyAudioCtx;
                        const now = ctx.currentTime;
                        [523.25, 659.25, 783.99].forEach((freq, i) => {
                            const osc = ctx.createOscillator();
                            const gain = ctx.createGain();
                            osc.type = 'sine';
                            osc.frequency.value = freq;
                            const t = now + (i * 0.09);
                            gain.gain.setValueAtTime(0.0001, t);
                            gain.gain.linearRampToValueAtTime(0.16, t + 0.02);
                            gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.55);
                            osc.connect(gain);
                            gain.connect(ctx.destination);
                            osc.start(t);
                            osc.stop(t + 0.6);
                        });
                    } catch (e) { /* ignore */ }
                },

                fireConfetti(count = 70) {
                    const container = this.$refs.lobbyConfetti;
                    if (!container) return;
                    const colors = ['#8b5cf6', '#ec4899', '#22c55e', '#f59e0b', '#3b82f6', '#ef4444', '#06b6d4'];
                    for (let i = 0; i < count; i++) {
                        const piece = document.createElement('div');
                        piece.className = 'lobby-confetti-piece';
                        piece.style.left = (Math.random() * 100) + 'vw';
                        piece.style.background = colors[Math.floor(Math.random() * colors.length)];
                        piece.style.animationDuration = (2.5 + Math.random() * 2) + 's';
                        piece.style.animationDelay = (Math.random() * 0.3) + 's';
                        const size = (6 + Math.random() * 8);
                        piece.style.width = size + 'px';
                        piece.style.height = size + 'px';
                        if (Math.random() > 0.5) { piece.style.borderRadius = '50%'; }
                        container.appendChild(piece);
                        setTimeout(() => piece.remove(), 5000);
                    }
                },

                lobbyInitials(name) {
                    if (!name) return '?';
                    const parts = name.trim().split(/\s+/).filter(Boolean);
                    if (parts.length === 0) return '?';
                    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
                    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
                },

                lobbyAvatarColor(name) {
                    const colors = [
                        'from-violet-500 to-purple-600', 'from-pink-500 to-rose-600',
                        'from-blue-500 to-cyan-600', 'from-emerald-500 to-green-600',
                        'from-amber-500 to-orange-600', 'from-indigo-500 to-blue-600',
                        'from-fuchsia-500 to-pink-600', 'from-teal-500 to-emerald-600',
                    ];
                    let hash = 0;
                    for (let i = 0; i < (name || '').length; i++) { hash = (name.charCodeAt(i) + ((hash << 5) - hash)) | 0; }
                    return colors[Math.abs(hash) % colors.length];
                },

                toggleFullscreen() {
                    this.initLobbyAudio();
                    if (document.fullscreenElement) {
                        document.exitFullscreen().catch(() => {});
                    } else if (document.documentElement.requestFullscreen) {
                        document.documentElement.requestFullscreen().catch(() => {});
                    }
                },
            }));
        });
    </script>
    @include('qr-registration.partials.lobby-styles')
</head>
<body class="bg-slate-950 text-white overflow-hidden">
    <div
        x-data="lobbyPage(@js([
            'qrData' => [
                'join_code' => $qrToken->join_code,
                'welcome_message' => $qrToken->welcome_message,
                'max_attendees' => $qrToken->max_attendees,
            ],
            'registerUrl' => $registerUrl,
            'attendeesUrl' => $attendeesUrl,
            'title' => $meeting->title,
            'orgName' => $orgName,
            'orgLogo' => $orgLogo,
            'appName' => $appName,
        ]))"
        x-init="init()"
        class="fixed inset-0 z-[90] overflow-hidden bg-slate-950 text-white"
        style="--lobby-primary: {{ $primaryColor }}; --lobby-secondary: {{ $secondaryColor }}; background-image: radial-gradient(circle at 20% 20%, color-mix(in srgb, var(--lobby-primary) 20%, transparent), transparent 45%), radial-gradient(circle at 85% 80%, color-mix(in srgb, var(--lobby-secondary) 18%, transparent), transparent 45%);"
    >
        @include('qr-registration.partials.lobby-stage', ['lobbyMode' => 'standalone'])
    </div>
</body>
</html>
