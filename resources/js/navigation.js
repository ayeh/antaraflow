export default function appState() {
    return {
        activeFlyout: null,
        commandPaletteOpen: false,
        commandQuery: '',
        commandSelectedIndex: 0,
        fabExpanded: false,
        theme: localStorage.getItem('theme') || 'system',
        bottomSheetOpen: false,
        recentMeetings: [],
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

        get filteredCommands() {
            const q = this.commandQuery.toLowerCase();
            return {
                nav: q
                    ? this.navCommands.filter(c => c.label.toLowerCase().includes(q))
                    : this.navCommands,
                meetings: q
                    ? this.recentMeetings.filter(m => m.title.toLowerCase().includes(q))
                    : this.recentMeetings.slice(0, 5),
            };
        },

        get commandResultCount() {
            return this.filteredCommands.nav.length + this.filteredCommands.meetings.length;
        },

        navigateCommand(direction) {
            if (direction === 'up' && this.commandSelectedIndex > 0) {
                this.commandSelectedIndex--;
            } else if (direction === 'down' && this.commandSelectedIndex < this.commandResultCount - 1) {
                this.commandSelectedIndex++;
            }
        },

        executeCommand(index) {
            const meetingLinks = this.filteredCommands.meetings.map(m => ({
                href: `/meetings/${m.id}`,
            }));
            const all = [...this.filteredCommands.nav, ...meetingLinks];
            const item = all[index ?? this.commandSelectedIndex];
            if (!item) { return; }
            this.commandPaletteOpen = false;
            window.location.href = item.href;
        },
    };
}
