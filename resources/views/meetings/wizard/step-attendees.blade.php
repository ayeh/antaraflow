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
                organizer: '{{ __("Organizer") }}',
                presenter: '{{ __("Presenter") }}',
                note_taker: '{{ __("Note Taker") }}',
                participant: '{{ __("Participant") }}',
                observer: '{{ __("Observer") }}',
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
                    this.successMessage = '{{ __('Guest added successfully.') }}';
                    setTimeout(() => this.successMessage = '', 3000);
                } else {
                    const data = await response.json().catch(() => null);
                    this.errorMessage = data?.message || 'Failed to add guest. Please try again.';
                    setTimeout(() => this.errorMessage = '', 4000);
                }
            } catch (e) {
                console.error('Failed to add guest:', e);
                this.errorMessage = '{{ __('Network error. Please try again.') }}';
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
                    this.successMessage = '{{ __('Member added successfully.') }}';
                    setTimeout(() => this.successMessage = '', 3000);
                } else {
                    const data = await response.json().catch(() => null);
                    this.errorMessage = data?.message || 'Failed to add member. Please try again.';
                    setTimeout(() => this.errorMessage = '', 4000);
                }
            } catch (e) {
                console.error('Failed to add org member:', e);
                this.errorMessage = '{{ __('Network error. Please try again.') }}';
                setTimeout(() => this.errorMessage = '', 4000);
            }

            this.loading = false;
        },

        async removeAttendee(attendeeId) {
            if (!(await window.antaraConfirm('{{ __("Remove this attendee?") }}', {title: '{{ __("Remove Attendee") }}'}))) return;
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
                    this.successMessage = '{{ __('Attendee removed.') }}';
                    setTimeout(() => this.successMessage = '', 3000);
                } else {
                    this.errorMessage = '{{ __('Failed to remove attendee.') }}';
                    setTimeout(() => this.errorMessage = '', 4000);
                }
            } catch (e) {
                console.error('Failed to remove attendee:', e);
                this.errorMessage = '{{ __('Network error. Please try again.') }}';
                setTimeout(() => this.errorMessage = '', 4000);
            }

            this.loading = false;
        },

        async importProjectMembers() {
            {{-- This endpoint is not yet implemented; placeholder for future use --}}
            this.errorMessage = '{{ __('Import from project is not yet available.') }}';
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
        lobbyAppName: @js($branding->appName()),
        lobbyTaglines: @js([
            'Every meeting, minuted by AI.',
            'Turn conversations into decisions — automatically.',
            'This live check-in is just one small feature.',
            'Smart minutes & action items, handled for you.',
            'Meetings that write their own notes.',
            'From discussion to decisions in seconds.',
            "There's a smarter way to run meetings.",
            'Curious what else it can do?',
        ]),
        lobbyTagline: '',
        lobbyTaglineTimer: null,

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

        get qrUrl() {
            return this.qrData ? '{{ url('register') }}/' + this.qrData.token : null;
        },

        get lobbyShareUrl() {
            return this.qrData ? '{{ url('lobby') }}/' + this.qrData.token : null;
        },

        copyLobbyUrl() {
            if (this.lobbyShareUrl) {
                navigator.clipboard.writeText(this.lobbyShareUrl);
                this.successMessage = '{{ __('Live screen link copied!') }}';
                setTimeout(() => this.successMessage = '', 3000);
            }
        },

        shareLobbyVia(platform) {
            if (!this.lobbyShareUrl) return;
            const text = 'Watch the live registration lobby: ' + this.lobbyShareUrl;
            const urls = {
                whatsapp: 'https://wa.me/?text=' + encodeURIComponent(text),
                telegram: 'https://t.me/share/url?url=' + encodeURIComponent(this.lobbyShareUrl) + '&text=' + encodeURIComponent('Live registration lobby'),
                email: 'mailto:?subject=' + encodeURIComponent('Live Registration Lobby') + '&body=' + encodeURIComponent(text),
            };
            window.open(urls[platform], '_blank');
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
                    this.successMessage = '{{ __('QR registration activated.') }}';
                    setTimeout(() => this.successMessage = '', 3000);
                } else {
                    const data = await response.json().catch(() => null);
                    this.errorMessage = data?.message || 'Failed to generate QR code.';
                    setTimeout(() => this.errorMessage = '', 4000);
                }
            } catch (e) {
                console.error('QR generation failed:', e);
                this.errorMessage = '{{ __('Network error. Please try again.') }}';
                setTimeout(() => this.errorMessage = '', 4000);
            }

            this.qrLoading = false;
        },

        async disableQr() {
            if (!(await window.antaraConfirm('{{ __("Existing QR links will stop working.") }}', {title: '{{ __("Disable QR Registration") }}', confirmLabel: '{{ __("Disable") }}', isDanger: false}))) return;
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
                    this.successMessage = '{{ __('QR registration disabled.') }}';
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
                this.successMessage = '{{ __('Registration link copied!') }}';
                setTimeout(() => this.successMessage = '', 3000);
            }
        },

        copyJoinCode() {
            if (this.qrData?.join_code) {
                navigator.clipboard.writeText(this.qrData.join_code);
                this.successMessage = '{{ __('Join code copied!') }}';
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
            this.lobbyTagline = this.pickTagline();
            this.lobbyTaglineTimer = setInterval(() => { this.lobbyTagline = this.pickTagline(); }, 5500);
        },

        closeLobby() {
            if (this.lobbyPollId) { clearInterval(this.lobbyPollId); this.lobbyPollId = null; }
            if (this.lobbyLangTimer) { clearInterval(this.lobbyLangTimer); this.lobbyLangTimer = null; }
            if (this.lobbyTaglineTimer) { clearInterval(this.lobbyTaglineTimer); this.lobbyTaglineTimer = null; }
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
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Total Attendees') }}</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1" x-text="stats.total"></p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ __('registered') }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Present') }}</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1" x-text="stats.present"></p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ __('marked present') }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Absent') }}</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1" x-text="stats.absent"></p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ __('not present') }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Confirmed') }}</p>
            <p class="text-2xl font-bold text-violet-600 dark:text-violet-400 mt-1" x-text="stats.confirmed"></p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ __('RSVP accepted') }}</p>
        </div>
    </div>

    {{-- Two-Column Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left Panel: Attendees List (2/3 width) --}}
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
            {{-- Header --}}
            <div class="p-6 pb-4">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Attendees') }}</h2>
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
                                {{ __('Import Project Members') }}
                            </button>
                        @endif
                        <span class="text-sm text-gray-500 dark:text-gray-400" x-text="stats.total + ' total'"></span>
                    </div>
                </div>

                {{-- Filter Tabs --}}
                <div class="flex gap-1 bg-gray-100 dark:bg-slate-700/50 rounded-lg p-1">
                    <template x-for="tab in [{key: 'all', label: '{{ __("All") }}'}, {key: 'present', label: '{{ __("Present") }}'}, {key: 'absent', label: '{{ __("Absent") }}'}, {key: 'guests', label: '{{ __("Guests") }}'}]" :key="tab.key">
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
                        <h3 class="mt-3 text-sm font-medium text-gray-900 dark:text-white">{{ __('No attendees added') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            <span x-show="activeTab === 'all'">{{ __('Add attendees using the form on the right.') }}</span>
                            <span x-show="activeTab === 'present'" x-cloak>{{ __('No attendees are marked as present.') }}</span>
                            <span x-show="activeTab === 'absent'" x-cloak>{{ __('No absent attendees.') }}</span>
                            <span x-show="activeTab === 'guests'" x-cloak>{{ __('No guest attendees have been added.') }}</span>
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
                                            >{{ __('Guest') }}</span>
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
                                        title="{{ __('Remove attendee') }}"
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
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">{{ __('Add Attendee') }}</h3>

                    {{-- Mode Toggle Pills --}}
                    <div class="flex gap-1 mb-5">
                        <template x-for="mode in [{key: 'team', label: '{{ __("Team Members") }}'}, {key: 'org', label: '{{ __("Org Member") }}'}, {key: 'guest', label: '{{ __("Guest") }}'}]" :key="mode.key">
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
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Select team members to add') }}</p>
                            <div class="flex gap-2">
                                <button type="button" @click="selectAll()" class="text-xs text-violet-600 dark:text-violet-400 hover:underline">{{ __('Select All') }}</button>
                                <button type="button" @click="deselectAll()" x-show="selectedMembers.length > 0" class="text-xs text-gray-500 hover:underline">{{ __('Clear') }}</button>
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
                                placeholder="{{ __('Search members...') }}"
                                class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                            />
                        </div>

                        {{-- Member Checklist --}}
                        <div class="max-h-52 overflow-y-auto space-y-1 mb-4 border border-gray-200 dark:border-slate-700 rounded-lg p-2">
                            <template x-if="filteredOrgMembers.length === 0">
                                <p class="text-xs text-gray-500 dark:text-gray-400 text-center py-4">
                                    <span x-show="availableOrgMembers.length === 0">{{ __('All organization members have been added.') }}</span>
                                    <span x-show="availableOrgMembers.length > 0" x-cloak>{{ __('No members match your search.') }}</span>
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
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">{{ __('Search and select a member') }}</p>

                        {{-- Search --}}
                        <div class="relative mb-3">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input
                                type="text"
                                x-model="searchQuery"
                                placeholder="{{ __('Search by name or email...') }}"
                                class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                            />
                        </div>

                        {{-- Single Select List --}}
                        <div class="max-h-52 overflow-y-auto space-y-1 mb-4 border border-gray-200 dark:border-slate-700 rounded-lg p-2">
                            <template x-if="filteredOrgMembers.length === 0">
                                <p class="text-xs text-gray-500 dark:text-gray-400 text-center py-4">{{ __('No matching members found.') }}</p>
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
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Name') }} <span class="text-red-500">*</span></label>
                                <input
                                    type="text"
                                    x-model="guestName"
                                    placeholder="{{ __('Attendee name') }}"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Email') }}</label>
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
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Mark as Present') }}</span>
                        </label>

                        {{-- Role Dropdown --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Role') }}</label>
                            <select
                                x-model="role"
                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                            >
                                <option value="participant">{{ __('Participant') }}</option>
                                <option value="organizer">{{ __('Organizer') }}</option>
                                <option value="presenter">{{ __('Presenter') }}</option>
                                <option value="note_taker">{{ __('Note Taker') }}</option>
                                <option value="observer">{{ __('Observer') }}</option>
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
                            <p class="font-medium text-gray-900 dark:text-white">{{ __('QR Registration') }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Walk-in guest registration via QR code') }}</p>
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
                            {{ __('Set Up QR Registration') }}
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>

                    {{-- Setup: Registration Settings Form --}}
                    <div x-show="qrSetupMode" x-cloak class="space-y-4">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Registration Settings') }}</h4>

                        {{-- Expiration --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Expiration Date & Time') }}</label>
                            <input
                                type="datetime-local"
                                x-model="qrSettings.expires_at"
                                class="w-full px-3 py-1.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                            />
                        </div>

                        {{-- Max Attendees --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Max Attendees') }} <span class="text-gray-400">{{ __('(optional)') }}</span></label>
                            <input
                                type="number"
                                min="1"
                                x-model="qrSettings.max_attendees"
                                placeholder="{{ __('Unlimited') }}"
                                class="w-full px-3 py-1.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                            />
                        </div>

                        {{-- Required Fields --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Required Fields') }}</label>
                            <div class="grid grid-cols-2 gap-2">
                                <template x-for="field in [{key: 'name', label: '{{ __("Name") }}'}, {key: 'email', label: '{{ __("Email") }}'}, {key: 'phone', label: '{{ __("Phone") }}'}, {key: 'company', label: '{{ __("Company") }}'}]" :key="field.key">
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
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Welcome Message') }} <span class="text-gray-400">{{ __('(optional)') }}</span></label>
                            <textarea
                                x-model="qrSettings.welcome_message"
                                rows="3"
                                maxlength="500"
                                placeholder="{{ __('Welcome to the meeting! Please register below.') }}"
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
                            >{{ __('Cancel') }}</button>
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
                            {{ __('Present Live Lobby') }}
                        </button>

                        {{-- Share Live Lobby (for projector / client screen) --}}
                        <div class="rounded-lg border border-violet-200 dark:border-violet-800/60 bg-violet-50/60 dark:bg-violet-900/10 p-2.5 space-y-2">
                            <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                                Share this live screen to display on a projector or another device — no login needed.
                            </p>
                            <div class="flex items-center gap-2">
                                <input type="text" :value="lobbyShareUrl" readonly class="flex-1 px-3 py-1.5 text-xs border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-300 truncate" />
                                <button type="button" @click="copyLobbyUrl()" class="flex-shrink-0 p-1.5 text-gray-400 hover:text-violet-600 dark:hover:text-violet-400 rounded-md hover:bg-violet-100 dark:hover:bg-violet-900/20 transition-colors" title="{{ __('Copy live screen link') }}">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </button>
                            </div>
                            <div class="flex gap-2">
                                <a :href="lobbyShareUrl" target="_blank" rel="noopener" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 hover:bg-white dark:hover:bg-slate-700 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    Open
                                </a>
                                <button type="button" @click="shareLobbyVia('whatsapp')" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-800 hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2 22l4.983-1.395A9.953 9.953 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18c-1.66 0-3.2-.507-4.483-1.372l-.32-.192-3.32.93.973-3.234-.21-.337A7.95 7.95 0 014 12c0-4.411 3.589-8 8-8s8 3.589 8 8-3.589 8-8 8z"/></svg>
                                    WhatsApp
                                </button>
                                <button type="button" @click="shareLobbyVia('telegram')" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>
                                    Telegram
                                </button>
                            </div>
                        </div>

                        {{-- QR Code --}}
                        <div class="flex items-center justify-center p-3 bg-white rounded-lg border border-gray-200 dark:border-slate-600">
                            <img :src="'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent(qrUrl)" alt="QR Code" class="w-40 h-40" />
                        </div>

                        {{-- Join Code --}}
                        <div class="flex items-center justify-between p-2.5 bg-gray-50 dark:bg-slate-700/50 rounded-lg">
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Join Code') }}</p>
                                <p class="text-lg font-bold font-mono tracking-widest text-gray-900 dark:text-white" x-text="qrData?.join_code"></p>
                            </div>
                            <button type="button" @click="copyJoinCode()" class="p-1.5 text-gray-400 hover:text-violet-600 dark:hover:text-violet-400 rounded-md hover:bg-violet-50 dark:hover:bg-violet-900/20 transition-colors" title="{{ __('Copy code') }}">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </button>
                        </div>

                        {{-- Registration URL --}}
                        <div class="flex items-center gap-2">
                            <input type="text" :value="qrUrl" readonly class="flex-1 px-3 py-1.5 text-xs border border-gray-300 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-gray-700 dark:text-gray-300 truncate" />
                            <button type="button" @click="copyQrUrl()" class="flex-shrink-0 p-1.5 text-gray-400 hover:text-violet-600 dark:hover:text-violet-400 rounded-md hover:bg-violet-50 dark:hover:bg-violet-900/20 transition-colors" title="{{ __('Copy link') }}">
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
                                {{ __('Download QR') }}
                            </a>
                            <button type="button" @click="qrData = null; qrSetupMode = true; qrView = 'setup'" class="flex-1 inline-flex items-center justify-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                {{ __('Regenerate') }}
                            </button>
                        </div>
                        <button type="button" @click="disableQr()" :disabled="qrLoading" class="w-full px-3 py-1.5 text-xs font-medium rounded-lg text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors disabled:opacity-50">
                            {{ __('Disable Registration') }}
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
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('Meeting is not editable') }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ __('Attendees can only be modified in Draft or In Progress status.') }}</p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- ============================ QR LIVE LOBBY (full-screen) ============================ --}}
    @php($lobbyBranding = $branding->getForOrganization($meeting->organization))
    <div x-ref="lobbyScreen" x-show="lobbyOpen" x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[90] overflow-hidden bg-slate-950 text-white"
        style="--lobby-primary: {{ $lobbyBranding['primary_color'] ?? '#7c3aed' }}; --lobby-secondary: {{ $lobbyBranding['secondary_color'] ?? '#3b82f6' }}; background-image: radial-gradient(circle at 20% 20%, color-mix(in srgb, var(--lobby-primary) 20%, transparent), transparent 45%), radial-gradient(circle at 85% 80%, color-mix(in srgb, var(--lobby-secondary) 18%, transparent), transparent 45%);">

        @include('qr-registration.partials.lobby-stage', ['lobbyMode' => 'overlay'])
    </div>

    @include('qr-registration.partials.lobby-styles')
</div>
