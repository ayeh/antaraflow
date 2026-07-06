@php($lobbyMode = $lobbyMode ?? 'overlay')

{{-- Confetti layer --}}
<div x-ref="lobbyConfetti" class="pointer-events-none fixed inset-0 z-[60]"></div>

{{-- (#8) Milestone banner --}}
<div x-show="lobbyBanner" x-cloak
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
    class="absolute top-20 left-1/2 -translate-x-1/2 z-[75] pointer-events-none">
    <div class="px-6 py-3 rounded-full shadow-2xl text-lg md:text-2xl font-bold flex items-center gap-2" style="background-image: linear-gradient(to right, var(--lobby-primary), var(--lobby-secondary));">
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
            <p class="mt-6 text-xl md:text-3xl font-semibold" style="color: color-mix(in srgb, var(--lobby-primary) 45%, white);">Welcome,</p>
            <p class="mt-1 text-4xl md:text-7xl font-black leading-tight" x-text="lobbyHero.name"></p>
            <p x-show="lobbyHero.company" class="mt-2 text-lg md:text-2xl text-white/60" x-text="lobbyHero.company"></p>
            <p x-show="lobbyHero.extra > 0" class="mt-3 text-base md:text-xl font-semibold" style="color: color-mix(in srgb, var(--lobby-secondary) 55%, white);">
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
        @if ($lobbyMode === 'standalone')
            {{-- Fullscreen toggle (projector / shared view) --}}
            <button type="button" @click="toggleFullscreen()"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg bg-white/10 hover:bg-white/20 text-white/80 hover:text-white backdrop-blur transition-colors">
                <svg x-show="!isFullscreen" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V6a2 2 0 012-2h2M4 16v2a2 2 0 002 2h2m8-16h2a2 2 0 012 2v2m-4 12h2a2 2 0 002-2v-2"/></svg>
                <svg x-show="isFullscreen" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9L4 4m0 0v3m0-3h3m8 5l5-5m0 0v3m0-3h-3M9 15l-5 5m0 0v-3m0 3h3m8-5l5 5m0 0v-3m0 3h-3"/></svg>
                <span x-text="isFullscreen ? 'Exit fullscreen' : 'Fullscreen'"></span>
            </button>
        @else
            <button type="button" @click="closeLobby()"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg bg-white/10 hover:bg-white/20 text-white/80 hover:text-white backdrop-blur transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Exit
            </button>
        @endif
    </div>
</div>

<div class="relative z-10 h-full w-full flex flex-col px-6 pt-16 pb-8 md:px-12 md:pt-20 md:pb-10">
    {{-- Header --}}
    <div class="text-center shrink-0">
        {{-- (#6) Cycling multi-language welcome --}}
        <p class="text-xs md:text-sm font-semibold uppercase tracking-[0.3em]" style="color: color-mix(in srgb, var(--lobby-primary) 60%, white);">
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
            <div x-show="lobbyAttendees.length === 0" class="mt-5 lobby-bob inline-flex items-center gap-2 px-4 py-2 rounded-full border text-sm md:text-base font-medium" style="background-color: color-mix(in srgb, var(--lobby-primary) 20%, transparent); border-color: color-mix(in srgb, var(--lobby-primary) 35%, transparent); color: color-mix(in srgb, var(--lobby-primary) 45%, white);">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
                Point your camera here to check in
            </div>
            <div class="mt-6 text-center">
                <p class="text-xs md:text-sm uppercase tracking-widest text-white/50">Or enter join code</p>
                <p class="mt-1 text-4xl md:text-6xl font-black font-mono tracking-[0.2em] text-transparent bg-clip-text" style="background-image: linear-gradient(to right, color-mix(in srgb, var(--lobby-primary) 55%, white), color-mix(in srgb, var(--lobby-secondary) 55%, white));" x-text="qrData?.join_code"></p>
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
                                <stop offset="0%" style="stop-color: var(--lobby-primary);" />
                                <stop offset="100%" style="stop-color: var(--lobby-secondary);" />
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
                    <p class="text-3xl md:text-5xl font-black text-transparent bg-clip-text" style="background-image: linear-gradient(to right, color-mix(in srgb, var(--lobby-primary) 55%, white), color-mix(in srgb, var(--lobby-secondary) 55%, white));" x-text="lobbyWelcomeWord"></p>
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
