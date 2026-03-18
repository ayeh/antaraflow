export default function appState() {
    return {
        activeFlyout: null,
        commandPaletteOpen: false,
        commandQuery: '',
        commandSelectedIndex: 0,
        fabExpanded: false,
        theme: localStorage.getItem('theme') || 'system',
        sidebarCollapsed: localStorage.getItem('sidebar_collapsed') === 'true',
        bottomSheetOpen: false,
        recentMeetings: [],
        searchResults: { meetings: [], action_items: [], projects: [] },
        searchLoading: false,
        searchDebounceTimer: null,
        navCommands: [
            { label: 'Dashboard',    href: '/dashboard' },
            { label: 'Meetings',     href: '/meetings' },
            { label: 'New Meeting',  href: '/meetings/create' },
            { label: 'Action Items', href: '/action-items' },
            { label: 'Settings',     href: '/organizations' },
        ],

        init() {
            this.applyTheme(this.theme);
            window.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                    e.preventDefault();
                    this.commandPaletteOpen = true;
                    this.$nextTick(() => this.$refs.commandInput?.focus());
                }
                if (e.key === 'Escape') {
                    this.commandPaletteOpen = false;
                    this.activeFlyout    = null;
                    this.fabExpanded     = false;
                    this.bottomSheetOpen = false;
                }
            });
        },

        applyTheme(theme) {
            if (theme === 'system') {
                const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.classList.toggle('dark', isDark);
            } else {
                document.documentElement.classList.toggle('dark', theme === 'dark');
            }
            this.theme = theme;
            localStorage.setItem('theme', theme);
        },

        cycleTheme() {
            const themes = ['light', 'dark', 'system'];
            const next = themes[(themes.indexOf(this.theme) + 1) % 3];
            this.applyTheme(next);
        },

        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            localStorage.setItem('sidebar_collapsed', this.sidebarCollapsed);
        },

        searchGlobal() {
            clearTimeout(this.searchDebounceTimer);
            this.commandSelectedIndex = 0;

            if (this.commandQuery.length < 2) {
                this.searchResults = { meetings: [], action_items: [], projects: [] };
                this.searchLoading = false;
                return;
            }

            this.searchLoading = true;

            this.searchDebounceTimer = setTimeout(() => {
                fetch('/search?q=' + encodeURIComponent(this.commandQuery), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                })
                .then(r => r.json())
                .then(data => {
                    this.searchResults = data;
                    this.searchLoading = false;
                })
                .catch(() => {
                    this.searchLoading = false;
                });
            }, 300);
        },

        get filteredCommands() {
            const q = this.commandQuery.toLowerCase();
            const isSearching = q.length >= 2;

            return {
                nav: q
                    ? this.navCommands.filter(c => c.label.toLowerCase().includes(q))
                    : this.navCommands,
                meetings: isSearching
                    ? this.searchResults.meetings
                    : (q ? this.recentMeetings.filter(m => m.title.toLowerCase().includes(q)) : this.recentMeetings.slice(0, 5)),
                action_items: isSearching ? this.searchResults.action_items : [],
                projects: isSearching ? this.searchResults.projects : [],
            };
        },

        get commandResultCount() {
            return this.filteredCommands.nav.length
                + this.filteredCommands.meetings.length
                + this.filteredCommands.action_items.length
                + this.filteredCommands.projects.length;
        },

        navigateCommand(direction) {
            if (direction === 'up' && this.commandSelectedIndex > 0) {
                this.commandSelectedIndex--;
            } else if (direction === 'down' && this.commandSelectedIndex < this.commandResultCount - 1) {
                this.commandSelectedIndex++;
            }
        },

        executeCommand(index) {
            const allItems = [
                ...this.filteredCommands.nav,
                ...this.filteredCommands.meetings.map(m => ({ href: m.url || '/meetings/' + m.id })),
                ...this.filteredCommands.action_items.map(a => ({ href: a.url })),
                ...this.filteredCommands.projects.map(p => ({ href: p.url })),
            ];
            const item = allItems[index ?? this.commandSelectedIndex];
            if (!item?.href) { return; }
            this.commandPaletteOpen = false;
            window.location.href = item.href;
        },
    };
}
