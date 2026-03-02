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
                observer: 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300',
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
            if (!confirm('Remove this attendee?')) return;
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
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Total Attendees</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1" x-text="stats.total"></p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">registered</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Present</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1" x-text="stats.present"></p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">marked present</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Absent</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1" x-text="stats.absent"></p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">not present</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Confirmed</p>
            <p class="text-2xl font-bold text-violet-600 dark:text-violet-400 mt-1" x-text="stats.confirmed"></p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">RSVP accepted</p>
        </div>
    </div>

    {{-- Two-Column Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left Panel: Attendees List (2/3 width) --}}
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
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
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors disabled:opacity-50"
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
                <div class="flex gap-1 bg-gray-100 dark:bg-gray-700/50 rounded-lg p-1">
                    <template x-for="tab in [{key: 'all', label: 'All'}, {key: 'present', label: 'Present'}, {key: 'absent', label: 'Absent'}, {key: 'guests', label: 'Guests'}]" :key="tab.key">
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
                            <div class="flex items-center justify-between p-3 rounded-lg border border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    {{-- Avatar --}}
                                    <div class="relative flex-shrink-0">
                                        <div class="h-10 w-10 rounded-full bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center">
                                            <span class="text-sm font-semibold text-violet-700 dark:text-violet-300" x-text="initial(attendee.name)"></span>
                                        </div>
                                        {{-- Presence Indicator --}}
                                        <span
                                            class="absolute -bottom-0.5 -right-0.5 block h-3 w-3 rounded-full ring-2 ring-white dark:ring-gray-800"
                                            :class="attendee.is_present ? 'bg-green-400' : 'bg-gray-300 dark:bg-gray-600'"
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
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Add Attendee</h3>

                    {{-- Mode Toggle Pills --}}
                    <div class="flex gap-1 mb-5">
                        <template x-for="mode in [{key: 'team', label: 'Team Members'}, {key: 'org', label: 'Org Member'}, {key: 'guest', label: 'Guest'}]" :key="mode.key">
                            <button
                                type="button"
                                @click="addMode = mode.key; selectedMembers = []; searchQuery = ''"
                                :class="addMode === mode.key
                                    ? 'bg-violet-600 text-white'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
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
                                class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                            />
                        </div>

                        {{-- Member Checklist --}}
                        <div class="max-h-52 overflow-y-auto space-y-1 mb-4 border border-gray-200 dark:border-gray-700 rounded-lg p-2">
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
                                        class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-violet-600 focus:ring-violet-500 dark:bg-gray-700"
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
                                class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                            />
                        </div>

                        {{-- Single Select List --}}
                        <div class="max-h-52 overflow-y-auto space-y-1 mb-4 border border-gray-200 dark:border-gray-700 rounded-lg p-2">
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
                                        class="h-4 w-4 border-gray-300 dark:border-gray-600 text-violet-600 focus:ring-violet-500 dark:bg-gray-700"
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
                                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                                <input
                                    type="email"
                                    x-model="guestEmail"
                                    placeholder="attendee@example.com"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                                />
                            </div>
                        </div>
                    </div>

                    {{-- Shared Options: Mark Present + Role --}}
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-4 space-y-3">
                        {{-- Mark as Present --}}
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                x-model="markPresent"
                                class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-violet-600 focus:ring-violet-500 dark:bg-gray-700"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Mark as Present</span>
                        </label>

                        {{-- Role Dropdown --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Role</label>
                            <select
                                x-model="role"
                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-violet-500 focus:border-transparent"
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

                {{-- QR Registration Link --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center gap-3 text-sm">
                        <div class="flex-shrink-0 h-8 w-8 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                            <svg class="h-4 w-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">QR Registration</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Set up QR code for walk-in registration</p>
                        </div>
                    </div>
                </div>
            </div>
        @else
            {{-- Read-only: show empty right panel --}}
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
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
</div>
