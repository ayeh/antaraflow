// antaraFlow Starter Kit — Alpine.js App State

document.addEventListener('alpine:init', () => {
    Alpine.data('appState', () => ({
        // Sidebar
        sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true',
        activeFlyout: null,

        // Theme: 'light' | 'dark' | 'system'
        theme: localStorage.getItem('theme') || 'system',

        init() {
            this.applyTheme();
            this.$watch('theme', () => this.applyTheme());
        },

        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed);
            if (this.sidebarCollapsed) { this.activeFlyout = null; }
        },

        cycleTheme() {
            const order = ['light', 'dark', 'system'];
            const idx = order.indexOf(this.theme);
            this.theme = order[(idx + 1) % 3];
            localStorage.setItem('theme', this.theme);
        },

        applyTheme() {
            const isDark = this.theme === 'dark' ||
                (this.theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', isDark);
        },
    }));
});
