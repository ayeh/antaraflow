{{-- Step 2: Attendees --}}
<div
    x-data="{
        activeTab: 'all',
        addMode: 'team',
        selectedMembers: [],
        markPresent: false,
        role: 'participant',
        guestName: '',
        guestEmail: '',
        searchQuery: '',
        attendees: @js($meeting->attendees->load('user')->toArray()),
        orgMembers: @js($orgMembers->toArray()),
        loading: false,
        successMessage: '',
        errorMessage: '',

        get filteredAttendees() {
            switch (this.activeTab) {
                case 'present':
                    return this.attendees.filter(a => a.is_present);
                case 'absent':
                    return this.attendees.filter(a => !a.is_present);
                case 'guests':
                    return this.attendees.filter(a => a.is_external);
                default:
                    return this.attendees;
            }
        },

        get availableOrgMembers() {
            const attendeeEmails = this.attendees.map(a => a.email?.toLowerCase()).filter(Boolean);
            return this.orgMembers.filter(m =>
                !attendeeEmails.includes(m.email?.toLowerCase())
            );
        },

        get filteredOrgMembers() {
            let members = this.availableOrgMembers;
            if (this.searchQuery.trim()) {
                const q = this.searchQuery.toLowerCase();
                members = members.filter(m =>
                    m.name.toLowerCase().includes(q) || (m.email && m.email.toLowerCase().includes(q))
                );
            }
            return members;
        },

        get stats() {
            return {
                total: this.attendees.length,
                present: this.attendees.filter(a => a.is_present).length,
                absent: this.attendees.filter(a => !a.is_present).length,
                confirmed: this.attendees.filter(a => a.rsvp_status === 'accepted').length,
            };
        },

        selectAll() {
            this.selectedMembers = this.filteredOrgMembers.map(m => m.id);
        },

        deselectAll() {
            this.selectedMembers = [];
        },

        roleBadgeClasses(role) {
            const map = {
                organizer: 'bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300',
                presenter: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300',
                note_taker: 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300',
                participant: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
                observer: 'bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300',
            };
            return map[role] || map.participant;
        },

        roleLabel(role) {
            const map = {
                organizer: 'Organizer',
                presenter: 'Presenter',
                note_taker: 'Note Taker',
                participant: 'Participant',
                observer: 'Observer',
            };
            return map[role] || role;
        },

        initial(name) {
            return name ? name.charAt(0).toUpperCase() : '?';
        },

        csrfToken() {
            return document.querySelector('meta[name=csrf-token]')?.content || '';
        },

        async addSelectedMembers() {
            if (this.selectedMembers.length === 0) return;
            this.loading = true;
            this.errorMessage = '';
            this.successMessage = '';

            const baseUrl = '{{ route('meetings.attendees.store', $meeting) }}';
            let addedCount = 0;

            for (const memberId of this.selectedMembers) {
                const member = this.orgMembers.find(m => m.id === memberId);
                if (!member) continue;

                try {
                    const response = await fetch(baseUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken(),
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            name: member.name,
                            email: member.email,
                            role: this.role,
                            is_external: false,
                            is_present: this.markPresent,
                        }),
                        redirect: 'follow',
                    });

                    if (response.ok || response.redirected) {
                        this.attendees.push({
                            id: Date.now() + addedCount,
                            name: member.name,
                            email: member.email,
                            role: this.role,
                            is_present: this.markPresent,
                            is_external: false,
                            rsvp_status: 'pending',
                            user_id: member.id,
                            user: member,
                        });
                        addedCount++;
                    }
                } catch (e) {
                    console.error('Failed to add member:', e);
                }
            }

            this.selectedMembers = [];
            this.markPresent = false;
            this.role = 'participant';
            this.loading = false;

            if (addedCount > 0) {
                this.successMessage = addedCount + ' member(s) added successfully.';
                setTimeout(() => this.successMessage = '', 3000);
            }
        },

        async addGuest() {
            if (!this.guestName.trim()) return;
            this.loading = true;
            this.errorMessage = '';
            this.successMessage = '';

            const baseUrl = '{{ route('meetings.attendees.store', $meeting) }}';

            try {
                const response = await fetch(baseUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        name: this.guestName,
                        email: this.guestEmail || null,
                        role: this.role,
                        is_external: true,
                        is_present: this.markPresent,
                    }),
                    redirect: 'follow',
                });

                if (response.ok || response.redirected) {
                    this.attendees.push({
                        id: Date.now(),
                        name: this.guestName,
                        email: this.guestEmail || null,
                        role: this.role,
                        is_present: this.markPresent,
                        is_external: true,
                        rsvp_status: 'pending',
                        user_id: null,
                        user: null,
                    });
                    this.guestName = '';
                    this.guestEmail = '';
                    this.markPresent = false;
                    this.role = 'participant';
                    this.successMessage = 'Guest added successfully.';
                    setTimeout(() => this.successMessage = '', 3000);
                } else {
                    const data = await response.json().catch(() => null);
                    this.errorMessage = data?.message || 'Failed to add guest. Please try again.';
                    setTimeout(() => this.errorMessage = '', 4000);
                }
            } catch (e) {
                console.error('Failed to add guest:', e);
                this.errorMessage = 'Network error. Please try again.';
                setTimeout(() => this.errorMessage = '', 4000);
            }

            this.loading = false;
        },

        async addOrgMember() {
            if (this.selectedMembers.length === 0) return;

            const memberId = this.selectedMembers[0];
            const member = this.orgMembers.find(m => m.id === memberId);
            if (!member) return;

            this.loading = true;
            this.errorMessage = '';
            this.successMessage = '';

            const baseUrl = '{{ route('meetings.attendees.store', $meeting) }}';

            try {
                const response = await fetch(baseUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        name: member.name,
                        email: member.email,
                        role: this.role,
                        is_external: false,
                        is_present: this.markPresent,
                    }),
                    redirect: 'follow',
                });

                if (response.ok || response.redirected) {
                    this.attendees.push({
                        id: Date.now(),
                        name: member.name,
                        email: member.email,
                        role: this.role,
                        is_present: this.markPresent,
                        is_external: false,
                        rsvp_status: 'pending',
                        user_id: member.id,
                        user: member,
                    });
                    this.selectedMembers = [];
                    this.searchQuery = '';
                    this.markPresent = false;
                    this.role = 'participant';
                    this.successMessage = 'Member added successfully.';
                    setTimeout(() => this.successMessage = '', 3000);
                } else {
                    const data = await response.json().catch(() => null);
                    this.errorMessage = data?.message || 'Failed to add member. Please try again.';
                    setTimeout(() => this.errorMessage = '', 4000);
                }
            } catch (e) {
                console.error('Failed to add org member:', e);
                this.errorMessage = 'Network error. Please try again.';
                setTimeout(() => this.errorMessage = '', 4000);
            }

            this.loading = false;
        },

        async removeAttendee(attendeeId) {
            if (!(await window.antaraConfirm('Remove this attendee?', {title: 'Remove Attendee'}))) return;
            this.loading = true;
            this.errorMessage = '';

            const baseUrl = '{{ url('/meetings/' . $meeting->id . '/attendees') }}/' + attendeeId;

            try {
                const response = await fetch(baseUrl, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    redirect: 'follow',
                });

                if (response.ok || response.redirected) {
                    this.attendees = this.attendees.filter(a => a.id !== attendeeId);
                    this.successMessage = 'Attendee removed.';
                    setTimeout(() => this.successMessage = '', 3000);
                } else {
                    this.errorMessage = 'Failed to remove attendee.';
                    setTimeout(() => this.errorMessage = '', 4000);
                }
            } catch (e) {
                console.error('Failed to remove attendee:', e);
                this.errorMessage = 'Network error. Please try again.';
                setTimeout(() => this.errorMessage = '', 4000);
            }

            this.loading = false;
        },

        async importProjectMembers() {
            {{-- This endpoint is not yet implemented; placeholder for future use --}}
            this.errorMessage = 'Import from project is not yet available.';
            setTimeout(() => this.errorMessage = '', 4000);
        },

        // QR Registration state
        qrView: @js($meeting->qrRegistrationTokens()->where('is_active', true)->exists() ? 'preview' : 'setup'),
        qrSetupMode: false,
        qrLoading: false,
        qrData: @js($meeting->qrRegistrationTokens()->where('is_active', true)->first()?->toArray()),
        qrSettings: {
            expires_at: '',
            max_attendees: '',
            required_fields: ['name'],
            welcome_message: '',
        },

        // QR Lobby (presentation) state
        lobbyOpen: false,
        lobbyTitle: @js($meeting->title),
        lobbyOrgName: @js($meeting->organization?->name),
        lobbyOrgLogo: @js($meeting->organization?->logo_path ? Storage::url($meeting->organization->logo_path) : null),
        lobbyAttendees: [],
        lobbyCount: 0,
        lobbyMax: null,
        lobbyPollId: null,
        lobbyLoaded: false,
        countPulse: false,
        lobbySoundOn: true,
        lobbyAudioCtx: null,
        lobbyHero: null,
        lobbyHeroTimer: null,
        lobbyBanner: null,
        lobbyBannerTimer: null,
        lobbyNewIds: [],
        lobbyLangs: ['Welcome', 'Selamat Datang', '欢迎', 'नमस्ते', 'مرحبا', 'ようこそ', 'Bienvenue'],
        lobbyLangIndex: 0,
        lobbyLangTimer: null,

        get qrUrl() {
            return this.qrData ? '{{ url('register') }}/' + this.qrData.token : null;
        },

        async generateQr() {
            this.qrLoading = true;
            this.errorMessage = '';

            try {
                const response = await fetch('{{ route('meetings.qr-registration.generate', $meeting) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.qrSettings),
                });

                if (response.ok) {
                    const data = await response.json();
                    this.qrData = data;
                    this.qrView = 'preview';
                    this.qrSetupMode = false;
                    this.successMessage = 'QR registration activated.';
                    setTimeout(() => this.successMessage = '', 3000);
                } else {
                    const data = await response.json().catch(() => null);
                    this.errorMessage = data?.message || 'Failed to generate QR code.';
                    setTimeout(() => this.errorMessage = '', 4000);
                }
            } catch (e) {
                console.error('QR generation failed:', e);
                this.errorMessage = 'Network error. Please try again.';
                setTimeout(() => this.errorMessage = '', 4000);
            }

            this.qrLoading = false;
        },

        async disableQr() {
            if (!(await window.antaraConfirm('Existing QR links will stop working.', {title: 'Disable QR Registration', confirmLabel: 'Disable', isDanger: false}))) return;
            this.qrLoading = true;

            try {
                const response = await fetch('{{ route('meetings.qr-registration.disable', $meeting) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                });

                if (response.ok) {
                    this.qrData = null;
                    this.qrView = 'setup';
                    this.successMessage = 'QR registration disabled.';
                    setTimeout(() => this.successMessage = '', 3000);
                }
            } catch (e) {
                console.error('Disable failed:', e);
            }

            this.qrLoading = false;
        },

        copyQrUrl() {
            if (this.qrUrl) {
                navigator.clipboard.writeText(this.qrUrl);
                this.successMessage = 'Registration link copied!';
                setTimeout(() => this.successMessage = '', 3000);
            }
        },

        copyJoinCode() {
            if (this.qrData?.join_code) {
                navigator.clipboard.writeText(this.qrData.join_code);
                this.successMessage = 'Join code copied!';
                setTimeout(() => this.successMessage = '', 3000);
            }
        },

        shareVia(platform) {
            if (!this.qrUrl) return;
            const text = 'Join the meeting: ' + this.qrUrl;
            const urls = {
                whatsapp: 'https://wa.me/?text=' + encodeURIComponent(text),
                telegram: 'https://t.me/share/url?url=' + encodeURIComponent(this.qrUrl) + '&text=' + encodeURIComponent('Join the meeting'),
                email: 'mailto:?subject=' + encodeURIComponent('Meeting Registration') + '&body=' + encodeURIComponent(text),
            };
            window.open(urls[platform], '_blank');
        },

        toggleRequiredField(field) {
            const idx = this.qrSettings.required_fields.indexOf(field);
            if (idx > -1) {
                this.qrSettings.required_fields.splice(idx, 1);
            } else {
                this.qrSettings.required_fields.push(field);
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

        get lobbyProgress() {
            if (!this.lobbyMax) { return null; }
            return Math.min(100, Math.round((this.lobbyCount / this.lobbyMax) * 100));
        },

        get lobbyRingOffset() {
            const circumference = 339.292; // 2 * PI * r (r = 54)
            return circumference - (circumference * (this.lobbyProgress ?? 0) / 100);
        },

        get lobbyWelcomeWord() {
            return this.lobbyLangs[this.lobbyLangIndex];
        },

        async openLobby() {
            if (!this.qrData) return;
            this.lobbyOpen = true;
            this.lobbyLoaded = false;
            this.lobbyAttendees = [];
            this.lobbyCount = 0;
            this.lobbyHero = null;
            this.lobbyBanner = null;
            this.lobbyNewIds = [];
            this.initLobbyAudio();
            await this.$nextTick();
            const el = this.$refs.lobbyScreen;
            if (el && el.requestFullscreen) {
                el.requestFullscreen().catch(() => {});
            }
            await this.fetchLobby();
            this.lobbyPollId = setInterval(() => this.fetchLobby(), 3000);
            this.lobbyLangTimer = setInterval(() => {
                this.lobbyLangIndex = (this.lobbyLangIndex + 1) % this.lobbyLangs.length;
            }, 2200);
        },

        closeLobby() {
            if (this.lobbyPollId) { clearInterval(this.lobbyPollId); this.lobbyPollId = null; }
            if (this.lobbyLangTimer) { clearInterval(this.lobbyLangTimer); this.lobbyLangTimer = null; }
            if (this.lobbyHeroTimer) { clearTimeout(this.lobbyHeroTimer); }
            if (this.lobbyBannerTimer) { clearTimeout(this.lobbyBannerTimer); }
            if (document.fullscreenElement) { document.exitFullscreen().catch(() => {}); }
            this.lobbyOpen = false;
        },

        async fetchLobby() {
            try {
                const response = await fetch('{{ route('meetings.qr-registration.attendees', $meeting) }}', {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken() },
                });
                if (!response.ok) return;
                const data = await response.json();
                const incoming = data.attendees || [];
                const existingIds = new Set(this.lobbyAttendees.map(a => a.id));
                const fresh = incoming.filter(a => !existingIds.has(a.id));
                const prevCount = this.lobbyCount;

                // newest first for display
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
                this.celebrate('Halfway there!');
            }
            if (count >= this.lobbyMax && prev < this.lobbyMax) {
                this.celebrate("We're full! 🎉");
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
    }"
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
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Total Attendees</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1" x-text="stats.total"></p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">registered</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Present</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1" x-text="stats.present"></p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">marked present</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Absent</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1" x-text="stats.absent"></p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">not present</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Confirmed</p>
            <p class="text-2xl font-bold text-violet-600 dark:text-violet-400 mt-1" x-text="stats.confirmed"></p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">RSVP accepted</p>
        </div>
    </div>

    {{-- Two-Column Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left Panel: Attendees List (2/3 width) --}}
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
            {{-- Header --}}
            <div class="p-6 pb-4">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Attendees</h2>
                    <div class="flex items-center gap-2">
                        @if($meeting->project && $isEditable)
                            <button
                                type="button"
                                @click="importProjectMembers()"
                                :disabled="loading"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors disabled:opacity-50"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                Import Project Members
                            </button>
                        @endif
                        <span class="text-sm text-gray-500 dark:text-gray-400" x-text="stats.total + ' total'"></span>
                    </div>
                </div>

                {{-- Filter Tabs --}}
                <div class="flex gap-1 bg-gray-100 dark:bg-slate-700/50 rounded-lg p-1">
                    <template x-for="tab in [{key: 'all', label: 'All'}, {key: 'present', label: 'Present'}, {key: 'absent', label: 'Absent'}, {key: 'guests', label: 'Guests'}]" :key="tab.key">
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

            {{-- Attendee List --}}
            <div class="px-6 pb-6">
                {{-- Empty State --}}
                <template x-if="filteredAttendees.length === 0">
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <h3 class="mt-3 text-sm font-medium text-gray-900 dark:text-white">No attendees added</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            <span x-show="activeTab === 'all'">Add attendees using the form on the right.</span>
                            <span x-show="activeTab === 'present'" x-cloak>No attendees are marked as present.</span>
                            <span x-show="activeTab === 'absent'" x-cloak>No absent attendees.</span>
                            <span x-show="activeTab === 'guests'" x-cloak>No guest attendees have been added.</span>
                        </p>
                    </div>
                </template>

                {{-- Attendee Cards --}}
                <template x-if="filteredAttendees.length > 0">
                    <div class="space-y-2">
                        <template x-for="attendee in filteredAttendees" :key="attendee.id">
                            <div class="flex items-center justify-between p-3 rounded-lg border border-gray-100 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    {{-- Avatar --}}
                                    <div class="relative flex-shrink-0">
                                        <div class="h-10 w-10 rounded-full bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center">
                                            <span class="text-sm font-semibold text-violet-700 dark:text-violet-300" x-text="initial(attendee.name)"></span>
                                        </div>
                                        {{-- Presence Indicator --}}
                                        <span
                                            class="absolute -bottom-0.5 -right-0.5 block h-3 w-3 rounded-full ring-2 ring-white dark:ring-gray-800"
                                            :class="attendee.is_present ? 'bg-green-400' : 'bg-gray-300 dark:bg-slate-600'"
                                        ></span>
                                    </div>

                                    {{-- Name & Email --}}
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="attendee.name"></p>
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                :class="roleBadgeClasses(attendee.role)"
                                                x-text="roleLabel(attendee.role)"
                                            ></span>
                                            <span
                                                x-show="attendee.is_external"
                                                x-cloak
                                                class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300"
                                            >Guest</span>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate" x-text="attendee.email || 'No email'"></p>
                                    </div>
                                </div>

                                {{-- Actions --}}
                                @if($isEditable)
                                    <button
                                        type="button"
                                        @click="removeAttendee(attendee.id)"
                                        :disabled="loading"
                                        class="flex-shrink-0 p-1.5 text-gray-400 hover:text-red-500 dark:hover:text-red-400 rounded-md hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors disabled:opacity-50"
                                        title="Remove attendee"
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

        {{-- Right Panel: Add Attendee Form (1/3 width) --}}
        @if($isEditable)
            <div class="lg:col-span-1 space-y-4">
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Add Attendee</h3>

                    {{-- Mode Toggle Pills --}}
                    <div class="flex gap-1 mb-5">
                        <template x-for="mode in [{key: 'team', label: 'Team Members'}, {key: 'org', label: 'Org Member'}, {key: 'guest', label: 'Guest'}]" :key="mode.key">
                            <button
                                type="button"
                                @click="addMode = mode.key; selectedMembers = []; searchQuery = ''"
                                :class="addMode === mode.key
                                    ? 'bg-violet-600 text-white'
                                    : 'bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                                class="flex-1 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors text-center"
                                x-text="mode.label"
                            ></button>
                        </template>
                    </div>

                    {{-- Team Members Mode --}}
                    <div x-show="addMode === 'team'" x-cloak>
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Select team members to add</p>
                            <div class="flex gap-2">
                                <button type="button" @click="selectAll()" class="text-xs text-violet-600 dark:text-violet-400 hover:underline">Select All</button>
                                <button type="button" @click="deselectAll()" x-show="selectedMembers.length > 0" class="text-xs text-gray-500 hover:underline">Clear</button>
                            </div>
                        </div>

                        {{-- Search --}}
                        <div class="relative mb-3">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input
                                type="text"
                                x-model="searchQuery"
                                placeholder="Search members..."
                                class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                            />
                        </div>

                        {{-- Member Checklist --}}
                        <div class="max-h-52 overflow-y-auto space-y-1 mb-4 border border-gray-200 dark:border-slate-700 rounded-lg p-2">
                            <template x-if="filteredOrgMembers.length === 0">
                                <p class="text-xs text-gray-500 dark:text-gray-400 text-center py-4">
                                    <span x-show="availableOrgMembers.length === 0">All organization members have been added.</span>
                                    <span x-show="availableOrgMembers.length > 0" x-cloak>No members match your search.</span>
                                </p>
                            </template>
                            <template x-for="member in filteredOrgMembers" :key="member.id">
                                <label class="flex items-center gap-2.5 p-2 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        :value="member.id"
                                        x-model.number="selectedMembers"
                                        class="h-4 w-4 rounded border-gray-300 dark:border-slate-600 text-violet-600 focus:ring-violet-500 dark:bg-slate-700"
                                    />
                                    <div class="min-w-0">
                                        <p class="text-sm text-gray-900 dark:text-white truncate" x-text="member.name"></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate" x-text="member.email"></p>
                                    </div>
                                </label>
                            </template>
                        </div>

                        {{-- Selected count --}}
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3" x-show="selectedMembers.length > 0" x-cloak>
                            <span x-text="selectedMembers.length"></span> member(s) selected
                        </p>
                    </div>

                    {{-- Organization Member Mode --}}
                    <div x-show="addMode === 'org'" x-cloak>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Search and select a member</p>

                        {{-- Search --}}
                        <div class="relative mb-3">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input
                                type="text"
                                x-model="searchQuery"
                                placeholder="Search by name or email..."
                                class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                            />
                        </div>

                        {{-- Single Select List --}}
                        <div class="max-h-52 overflow-y-auto space-y-1 mb-4 border border-gray-200 dark:border-slate-700 rounded-lg p-2">
                            <template x-if="filteredOrgMembers.length === 0">
                                <p class="text-xs text-gray-500 dark:text-gray-400 text-center py-4">No matching members found.</p>
                            </template>
                            <template x-for="member in filteredOrgMembers" :key="member.id">
                                <label class="flex items-center gap-2.5 p-2 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer">
                                    <input
                                        type="radio"
                                        name="org_member_select"
                                        :value="member.id"
                                        x-model.number="selectedMembers[0]"
                                        @change="selectedMembers = [member.id]"
                                        class="h-4 w-4 border-gray-300 dark:border-slate-600 text-violet-600 focus:ring-violet-500 dark:bg-slate-700"
                                    />
                                    <div class="min-w-0">
                                        <p class="text-sm text-gray-900 dark:text-white truncate" x-text="member.name"></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate" x-text="member.email"></p>
                                    </div>
                                </label>
                            </template>
                        </div>
                    </div>

                    {{-- Guest Mode --}}
                    <div x-show="addMode === 'guest'" x-cloak>
                        <div class="space-y-3 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name <span class="text-red-500">*</span></label>
                                <input
                                    type="text"
                                    x-model="guestName"
                                    placeholder="Attendee name"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                                <input
                                    type="email"
                                    x-model="guestEmail"
                                    placeholder="attendee@example.com"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                                />
                            </div>
                        </div>
                    </div>

                    {{-- Shared Options: Mark Present + Role --}}
                    <div class="border-t border-gray-200 dark:border-slate-700 pt-4 mt-4 space-y-3">
                        {{-- Mark as Present --}}
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                x-model="markPresent"
                                class="h-4 w-4 rounded border-gray-300 dark:border-slate-600 text-violet-600 focus:ring-violet-500 dark:bg-slate-700"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Mark as Present</span>
                        </label>

                        {{-- Role Dropdown --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Role</label>
                            <select
                                x-model="role"
                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                            >
                                <option value="participant">Participant</option>
                                <option value="organizer">Organizer</option>
                                <option value="presenter">Presenter</option>
                                <option value="note_taker">Note Taker</option>
                                <option value="observer">Observer</option>
                            </select>
                        </div>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="mt-5">
                        {{-- Team mode: Add Selected Members --}}
                        <button
                            x-show="addMode === 'team'"
                            type="button"
                            @click="addSelectedMembers()"
                            :disabled="loading || selectedMembers.length === 0"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-white bg-violet-600 hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <svg x-show="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span x-text="loading ? 'Adding...' : 'Add Selected Members (' + selectedMembers.length + ')'"></span>
                        </button>

                        {{-- Org mode: Add Member --}}
                        <button
                            x-show="addMode === 'org'"
                            x-cloak
                            type="button"
                            @click="addOrgMember()"
                            :disabled="loading || selectedMembers.length === 0"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-white bg-violet-600 hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <svg x-show="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span x-text="loading ? 'Adding...' : 'Add Member'"></span>
                        </button>

                        {{-- Guest mode: Add Guest --}}
                        <button
                            x-show="addMode === 'guest'"
                            x-cloak
                            type="button"
                            @click="addGuest()"
                            :disabled="loading || !guestName.trim()"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-white bg-violet-600 hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <svg x-show="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span x-text="loading ? 'Adding...' : 'Add Guest'"></span>
                        </button>
                    </div>
                </div>

                {{-- QR Registration --}}
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
                    <div class="flex items-center gap-3 text-sm mb-3">
                        <div class="flex-shrink-0 h-8 w-8 bg-violet-100 dark:bg-violet-900/30 rounded-lg flex items-center justify-center">
                            <svg class="h-4 w-4 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="font-medium text-gray-900 dark:text-white">QR Registration</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Walk-in guest registration via QR code</p>
                        </div>
                        <template x-if="qrData && qrView === 'preview'">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300">
                                <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> Active
                            </span>
                        </template>
                    </div>

                    {{-- Setup: Initial prompt to configure --}}
                    <div x-show="qrView === 'setup' && !qrSetupMode">
                        <button
                            type="button"
                            @click="qrSetupMode = true"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-violet-700 dark:text-violet-300 bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-800 hover:bg-violet-100 dark:hover:bg-violet-900/30 transition-colors"
                        >
                            Set Up QR Registration
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>

                    {{-- Setup: Registration Settings Form --}}
                    <div x-show="qrSetupMode" x-cloak class="space-y-4">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Registration Settings</h4>

                        {{-- Expiration --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Expiration Date & Time</label>
                            <input
                                type="datetime-local"
                                x-model="qrSettings.expires_at"
                                class="w-full px-3 py-1.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                            />
                        </div>

                        {{-- Max Attendees --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Max Attendees <span class="text-gray-400">(optional)</span></label>
                            <input
                                type="number"
                                min="1"
                                x-model="qrSettings.max_attendees"
                                placeholder="Unlimited"
                                class="w-full px-3 py-1.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                            />
                        </div>

                        {{-- Required Fields --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Required Fields</label>
                            <div class="grid grid-cols-2 gap-2">
                                <template x-for="field in [{key: 'name', label: 'Name'}, {key: 'email', label: 'Email'}, {key: 'phone', label: 'Phone'}, {key: 'company', label: 'Company'}]" :key="field.key">
                                    <label class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                                           :class="qrSettings.required_fields.includes(field.key) ? 'border-violet-300 dark:border-violet-600 bg-violet-50 dark:bg-violet-900/20' : 'border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-gray-700'">
                                        <input
                                            type="checkbox"
                                            :checked="qrSettings.required_fields.includes(field.key)"
                                            @change="toggleRequiredField(field.key)"
                                            class="h-3.5 w-3.5 rounded border-gray-300 text-violet-600 focus:ring-violet-500"
                                        />
                                        <span class="text-xs text-gray-700 dark:text-gray-300" x-text="field.label"></span>
                                    </label>
                                </template>
                            </div>
                        </div>

                        {{-- Welcome Message --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Welcome Message <span class="text-gray-400">(optional)</span></label>
                            <textarea
                                x-model="qrSettings.welcome_message"
                                rows="3"
                                maxlength="500"
                                placeholder="Welcome to the meeting! Please register below."
                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-violet-500 focus:border-transparent resize-none"
                            ></textarea>
                            <p class="text-xs text-gray-400 mt-1 text-right" x-text="(qrSettings.welcome_message?.length || 0) + '/500'"></p>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="flex gap-2">
                            <button
                                type="button"
                                @click="qrSetupMode = false"
                                class="flex-1 px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                            >Cancel</button>
                            <button
                                type="button"
                                @click="generateQr()"
                                :disabled="qrLoading"
                                class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-white bg-violet-600 hover:bg-violet-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                <svg x-show="qrLoading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-text="qrLoading ? 'Saving...' : 'Save & Generate'"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Preview: QR Code & Details --}}
                    <div x-show="qrView === 'preview' && qrData" x-cloak class="space-y-3">
                        {{-- Present / Live Lobby --}}
                        <button type="button" @click="openLobby()"
                            class="group w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold rounded-lg text-white bg-gradient-to-r from-violet-600 to-fuchsia-600 hover:from-violet-700 hover:to-fuchsia-700 shadow-lg shadow-violet-500/25 hover:shadow-violet-500/40 transition-all duration-200 hover:-translate-y-0.5">
                            <svg class="w-4 h-4 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V6a2 2 0 012-2h2M4 16v2a2 2 0 002 2h2m8-16h2a2 2 0 012 2v2m-4 12h2a2 2 0 002-2v-2"/></svg>
                            Present Live Lobby
                        </button>

                        {{-- QR Code --}}
                        <div class="flex items-center justify-center p-3 bg-white rounded-lg border border-gray-200 dark:border-slate-600">
                            <img :src="'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent(qrUrl)" alt="QR Code" class="w-40 h-40" />
                        </div>

                        {{-- Join Code --}}
                        <div class="flex items-center justify-between p-2.5 bg-gray-50 dark:bg-slate-700/50 rounded-lg">
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Join Code</p>
                                <p class="text-lg font-bold font-mono tracking-widest text-gray-900 dark:text-white" x-text="qrData?.join_code"></p>
                            </div>
                            <button type="button" @click="copyJoinCode()" class="p-1.5 text-gray-400 hover:text-violet-600 dark:hover:text-violet-400 rounded-md hover:bg-violet-50 dark:hover:bg-violet-900/20 transition-colors" title="Copy code">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </button>
                        </div>

                        {{-- Registration URL --}}
                        <div class="flex items-center gap-2">
                            <input type="text" :value="qrUrl" readonly class="flex-1 px-3 py-1.5 text-xs border border-gray-300 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-gray-700 dark:text-gray-300 truncate" />
                            <button type="button" @click="copyQrUrl()" class="flex-shrink-0 p-1.5 text-gray-400 hover:text-violet-600 dark:hover:text-violet-400 rounded-md hover:bg-violet-50 dark:hover:bg-violet-900/20 transition-colors" title="Copy link">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </button>
                        </div>

                        {{-- Registration Counter --}}
                        <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span x-text="(qrData?.registrations_count || 0) + ' registered' + (qrData?.max_attendees ? ' / ' + qrData.max_attendees + ' max' : '')"></span>
                            <span x-text="qrData?.expires_at ? 'Expires ' + new Date(qrData.expires_at).toLocaleDateString() : ''"></span>
                        </div>

                        {{-- Share Buttons --}}
                        <div class="flex gap-2">
                            <button type="button" @click="shareVia('whatsapp')" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-800 hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2 22l4.983-1.395A9.953 9.953 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18c-1.66 0-3.2-.507-4.483-1.372l-.32-.192-3.32.93.973-3.234-.21-.337A7.95 7.95 0 014 12c0-4.411 3.589-8 8-8s8 3.589 8 8-3.589 8-8 8z"/></svg>
                                WhatsApp
                            </button>
                            <button type="button" @click="shareVia('telegram')" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>
                                Telegram
                            </button>
                            <button type="button" @click="shareVia('email')" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-50 dark:bg-slate-700/50 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-slate-600 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                Email
                            </button>
                        </div>

                        {{-- Actions --}}
                        <div class="flex gap-2 pt-1">
                            <a :href="'https://api.qrserver.com/v1/create-qr-code/?size=400x400&format=png&download=1&data=' + encodeURIComponent(qrUrl)" target="_blank" class="flex-1 inline-flex items-center justify-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                Download QR
                            </a>
                            <button type="button" @click="qrData = null; qrSetupMode = true; qrView = 'setup'" class="flex-1 inline-flex items-center justify-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Regenerate
                            </button>
                        </div>
                        <button type="button" @click="disableQr()" :disabled="qrLoading" class="w-full px-3 py-1.5 text-xs font-medium rounded-lg text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors disabled:opacity-50">
                            Disable Registration
                        </button>
                    </div>
                </div>
            </div>
        @else
            {{-- Read-only: show empty right panel --}}
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                    <div class="text-center py-4">
                        <svg class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Meeting is not editable</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Attendees can only be modified in Draft or In Progress status.</p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- ============================ QR LIVE LOBBY (full-screen) ============================ --}}
    <div x-ref="lobbyScreen" x-show="lobbyOpen" x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[90] overflow-hidden bg-slate-950 text-white"
        style="background-image: radial-gradient(circle at 20% 20%, rgba(139,92,246,0.18), transparent 45%), radial-gradient(circle at 85% 80%, rgba(236,72,153,0.16), transparent 45%);">

        {{-- Confetti layer --}}
        <div x-ref="lobbyConfetti" class="pointer-events-none fixed inset-0 z-[60]"></div>

        {{-- (#8) Milestone banner --}}
        <div x-show="lobbyBanner" x-cloak
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="absolute top-20 left-1/2 -translate-x-1/2 z-[75] pointer-events-none">
            <div class="px-6 py-3 rounded-full bg-gradient-to-r from-violet-600 to-fuchsia-600 shadow-2xl shadow-fuchsia-900/40 text-lg md:text-2xl font-bold flex items-center gap-2">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.4 7.4H22l-6 4.6 2.3 7.4L12 16.9 5.7 21.4 8 14 2 9.4h7.6z"/></svg>
                <span x-text="lobbyBanner"></span>
            </div>
        </div>

        {{-- (#1) Hero welcome moment --}}
        <div x-show="lobbyHero" x-cloak class="absolute inset-0 z-[80] flex items-center justify-center pointer-events-none">
            <div class="absolute inset-0 bg-slate-950/55 backdrop-blur-sm"></div>
            <template x-if="lobbyHero">
                <div class="lobby-hero-card relative text-center px-8">
                    <div class="mx-auto w-28 h-28 md:w-40 md:h-40 rounded-full bg-gradient-to-br flex items-center justify-center text-4xl md:text-6xl font-black text-white shadow-2xl ring-4 ring-white/20"
                        :class="lobbyAvatarColor(lobbyHero.name)" x-text="lobbyInitials(lobbyHero.name)"></div>
                    <p class="mt-6 text-xl md:text-3xl font-semibold text-violet-200">Welcome,</p>
                    <p class="mt-1 text-4xl md:text-7xl font-black leading-tight" x-text="lobbyHero.name"></p>
                    <p x-show="lobbyHero.company" class="mt-2 text-lg md:text-2xl text-white/60" x-text="lobbyHero.company"></p>
                    <p x-show="lobbyHero.extra > 0" class="mt-3 text-base md:text-xl text-fuchsia-300 font-semibold">
                        + <span x-text="lobbyHero.extra"></span> more just joined
                    </p>
                </div>
            </template>
        </div>

        {{-- (#7) Top bar: branding + controls --}}
        <div class="absolute top-0 inset-x-0 z-[70] flex items-center justify-between px-5 py-4 md:px-8">
            <div class="flex items-center gap-3 min-w-0">
                <img x-show="lobbyOrgLogo" :src="lobbyOrgLogo" :alt="lobbyOrgName"
                    class="w-9 h-9 md:w-11 md:h-11 rounded-xl object-cover border border-white/15 bg-white/5" />
                <span x-show="lobbyOrgName" class="text-sm md:text-base font-semibold text-white/70 truncate" x-text="lobbyOrgName"></span>
            </div>
            <div class="flex items-center gap-2">
                {{-- (#2) Sound toggle --}}
                <button type="button" @click="toggleLobbySound()"
                    :title="lobbySoundOn ? 'Sound on' : 'Sound off'"
                    class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white/10 hover:bg-white/20 text-white/80 hover:text-white backdrop-blur transition-colors">
                    <svg x-show="lobbySoundOn" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072M17.95 6.05a8 8 0 010 11.9M5 9v6h4l5 4V5L9 9H5z"/></svg>
                    <svg x-show="!lobbySoundOn" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l4-4m0 4l-4-4M5 9v6h4l5 4V5L9 9H5z"/></svg>
                </button>
                <button type="button" @click="closeLobby()"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg bg-white/10 hover:bg-white/20 text-white/80 hover:text-white backdrop-blur transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Exit
                </button>
            </div>
        </div>

        <div class="relative z-10 h-full w-full flex flex-col px-6 pt-16 pb-8 md:px-12 md:pt-20 md:pb-10">
            {{-- Header --}}
            <div class="text-center shrink-0">
                {{-- (#6) Cycling multi-language welcome --}}
                <p class="text-xs md:text-sm font-semibold uppercase tracking-[0.3em] text-violet-300/80">
                    <span x-text="lobbyWelcomeWord"></span> · Scan to Join
                </p>
                <h1 class="mt-2 text-2xl md:text-4xl lg:text-5xl font-bold leading-tight" x-text="lobbyTitle"></h1>
                <p x-show="qrData?.welcome_message" x-cloak class="mt-2 text-sm md:text-lg text-white/60 max-w-2xl mx-auto" x-text="qrData?.welcome_message"></p>
            </div>

            {{-- Body --}}
            <div class="flex-1 min-h-0 mt-6 md:mt-10 grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 items-center">
                {{-- Scan side --}}
                <div class="flex flex-col items-center justify-center">
                    {{-- (#3) QR with animated glow ring --}}
                    <div class="lobby-qr-glow relative rounded-3xl">
                        <div class="relative bg-white rounded-3xl p-5 md:p-7 shadow-2xl shadow-violet-900/40">
                            <img :src="'https://api.qrserver.com/v1/create-qr-code/?size=480x480&margin=8&data=' + encodeURIComponent(qrUrl)"
                                alt="Scan to register" class="w-52 h-52 md:w-72 md:h-72 lg:w-80 lg:h-80" />
                        </div>
                    </div>
                    {{-- (#6) Idle bobbing scan prompt --}}
                    <div x-show="lobbyAttendees.length === 0" class="mt-5 lobby-bob inline-flex items-center gap-2 px-4 py-2 rounded-full bg-violet-500/20 border border-violet-400/30 text-violet-200 text-sm md:text-base font-medium">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
                        Point your camera here to check in
                    </div>
                    <div class="mt-6 text-center">
                        <p class="text-xs md:text-sm uppercase tracking-widest text-white/50">Or enter join code</p>
                        <p class="mt-1 text-4xl md:text-6xl font-black font-mono tracking-[0.2em] bg-gradient-to-r from-violet-300 to-fuchsia-300 bg-clip-text text-transparent" x-text="qrData?.join_code"></p>
                    </div>
                </div>

                {{-- People side --}}
                <div class="flex flex-col h-full min-h-0">
                    {{-- Counter / (#5) progress ring --}}
                    <div class="shrink-0 flex items-center gap-5 justify-center md:justify-start">
                        {{-- With max: circular progress ring --}}
                        <div x-show="lobbyMax" x-cloak class="relative w-32 h-32 md:w-40 md:h-40 shrink-0">
                            <svg class="w-full h-full -rotate-90" viewBox="0 0 120 120">
                                <circle cx="60" cy="60" r="54" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="10" />
                                <circle cx="60" cy="60" r="54" fill="none" stroke="url(#lobbyRingGrad)" stroke-width="10" stroke-linecap="round"
                                    stroke-dasharray="339.292" :stroke-dashoffset="lobbyRingOffset" class="transition-all duration-700 ease-out" />
                                <defs>
                                    <linearGradient id="lobbyRingGrad" x1="0" y1="0" x2="1" y2="1">
                                        <stop offset="0%" stop-color="#8b5cf6" />
                                        <stop offset="100%" stop-color="#ec4899" />
                                    </linearGradient>
                                </defs>
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                <span class="text-4xl md:text-5xl font-black tabular-nums transition-transform duration-300"
                                    :class="countPulse ? 'scale-125 text-emerald-300' : 'text-white'" x-text="lobbyCount"></span>
                                <span class="text-xs md:text-sm text-white/40">of <span x-text="lobbyMax"></span></span>
                            </div>
                        </div>
                        {{-- Without max: plain big counter --}}
                        <div x-show="!lobbyMax" class="text-center md:text-left">
                            <p class="text-xs md:text-sm uppercase tracking-widest text-white/50">Registered</p>
                            <span class="text-5xl md:text-7xl font-black tabular-nums transition-transform duration-300 inline-block"
                                :class="countPulse ? 'scale-125 text-emerald-300' : 'text-white'" x-text="lobbyCount"></span>
                        </div>
                        {{-- Label beside ring --}}
                        <div x-show="lobbyMax" x-cloak>
                            <p class="text-xs md:text-sm uppercase tracking-widest text-white/50">Checked In</p>
                            <p class="text-lg md:text-2xl font-bold text-white" x-text="(lobbyProgress ?? 0) + '%'"></p>
                            <p class="text-xs md:text-sm text-white/40" x-show="lobbyProgress >= 100">Registration full</p>
                        </div>
                    </div>

                    {{-- Attendee grid --}}
                    <div class="flex-1 min-h-0 mt-5 overflow-y-auto pr-1">
                        {{-- (#6) Empty attract state --}}
                        <div x-show="lobbyAttendees.length === 0" class="h-full flex flex-col items-center justify-center text-center text-white/50">
                            <p class="text-3xl md:text-5xl font-black bg-gradient-to-r from-violet-300 to-fuchsia-300 bg-clip-text text-transparent" x-text="lobbyWelcomeWord"></p>
                            <div class="mt-5 w-14 h-14 rounded-full border-2 border-dashed border-white/20 flex items-center justify-center animate-pulse">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            </div>
                            <p class="mt-3 text-sm md:text-base">Waiting for the first guest to scan…</p>
                        </div>

                        <div x-show="lobbyAttendees.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <template x-for="att in lobbyAttendees" :key="att.id">
                                {{-- (#4) Newest highlight via lobby-card-new --}}
                                <div class="lobby-attendee-card flex items-center gap-3 p-3 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-sm transition-shadow"
                                    :class="lobbyNewIds.includes(att.id) ? 'lobby-card-new' : ''">
                                    <div class="shrink-0 w-11 h-11 md:w-12 md:h-12 rounded-full bg-gradient-to-br flex items-center justify-center font-bold text-white text-sm md:text-base shadow-lg"
                                        :class="lobbyAvatarColor(att.name)" x-text="lobbyInitials(att.name)"></div>
                                    <div class="min-w-0">
                                        <p class="font-semibold truncate text-base md:text-lg" x-text="att.name"></p>
                                        <p x-show="att.company" class="text-xs md:text-sm text-white/50 truncate" x-text="att.company"></p>
                                    </div>
                                    <span x-show="lobbyNewIds.includes(att.id)" class="ml-auto shrink-0 px-2 py-0.5 text-[10px] md:text-xs font-bold uppercase tracking-wide rounded-full bg-emerald-400/20 text-emerald-300">New</span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        @keyframes lobbyConfettiFall {
            0% { transform: translateY(-12vh) rotate(0deg); opacity: 1; }
            100% { transform: translateY(110vh) rotate(720deg); opacity: 0; }
        }
        .lobby-confetti-piece {
            position: fixed; top: 0;
            animation-name: lobbyConfettiFall;
            animation-timing-function: ease-in;
            animation-fill-mode: forwards;
            pointer-events: none;
        }
        @keyframes lobbyPop {
            0% { transform: scale(0.6) translateY(14px); opacity: 0; }
            60% { transform: scale(1.05) translateY(-2px); opacity: 1; }
            100% { transform: scale(1) translateY(0); opacity: 1; }
        }
        .lobby-attendee-card { animation: lobbyPop 0.5s cubic-bezier(0.22, 1, 0.36, 1) both; }

        /* (#4) Newest registrant highlight */
        @keyframes lobbyNewGlow {
            0%, 100% { box-shadow: 0 0 0 1px rgba(52,211,153,0.4), 0 0 18px rgba(52,211,153,0.25); }
            50% { box-shadow: 0 0 0 2px rgba(52,211,153,0.7), 0 0 28px rgba(52,211,153,0.5); }
        }
        .lobby-card-new {
            border-color: rgba(52,211,153,0.6) !important;
            animation: lobbyNewGlow 1.4s ease-in-out infinite;
        }

        /* (#3) QR breathing glow */
        @keyframes lobbyQrGlow {
            0%, 100% { box-shadow: 0 0 28px 6px rgba(139,92,246,0.35), 0 0 60px 12px rgba(236,72,153,0.18); }
            50% { box-shadow: 0 0 44px 12px rgba(139,92,246,0.6), 0 0 90px 20px rgba(236,72,153,0.32); }
        }
        .lobby-qr-glow { animation: lobbyQrGlow 2.8s ease-in-out infinite; }

        /* (#1) Hero welcome card */
        @keyframes lobbyHeroIn {
            0% { transform: scale(0.7); opacity: 0; }
            55% { transform: scale(1.04); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }
        .lobby-hero-card { animation: lobbyHeroIn 0.5s cubic-bezier(0.22, 1, 0.36, 1) both; }

        /* (#6) Idle bobbing prompt */
        @keyframes lobbyBob {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }
        .lobby-bob { animation: lobbyBob 1.6s ease-in-out infinite; }

        @media (prefers-reduced-motion: reduce) {
            .lobby-qr-glow, .lobby-card-new, .lobby-bob, .lobby-hero-card, .lobby-attendee-card { animation: none; }
        }
    </style>
</div>
