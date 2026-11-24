import Alpine from 'alpinejs';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    Alpine.data('orbitShell', () => ({
        sidebarCollapsed: false,
        mobileMenuOpen: false,
        commandPaletteOpen: false,
        quickCreateOpen: false,
        darkMode: document.documentElement.classList.contains('dark'),
        searchQuery: '',
        searchResults: [],
        searchLoading: false,
        previewDrawerOpen: false,
        previewCandidate: null,
        previewLoading: false,

        init() {
            window.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    this.commandPaletteOpen = !this.commandPaletteOpen;
                    if (this.commandPaletteOpen) {
                        this.$nextTick(() => this.$refs.globalSearchInput?.focus());
                    }
                }
                if (e.key === 'Escape') {
                    this.commandPaletteOpen = false;
                    this.previewDrawerOpen = false;
                    this.quickCreateOpen = false;
                }
            });
        },

        toggleTheme() {
            this.darkMode = !this.darkMode;
            document.documentElement.classList.toggle('dark', this.darkMode);
        },

        async performGlobalSearch() {
            const q = this.searchQuery.trim();
            if (q.length < 2) {
                this.searchResults = [];
                return;
            }
            this.searchLoading = true;
            try {
                const res = await fetch(`/global-search?q=${encodeURIComponent(q)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                this.searchResults = data.results || [];
            } catch (e) {
                this.searchResults = [];
            } finally {
                this.searchLoading = false;
            }
        },

        async openCandidatePreview(candidateId) {
            this.previewDrawerOpen = true;
            this.previewLoading = true;
            this.previewCandidate = null;
            try {
                const res = await fetch(`/candidates/${candidateId}/preview`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    this.previewCandidate = await res.json();
                }
            } catch (e) {
                console.error(e);
            } finally {
                this.previewLoading = false;
            }
        }
    }));
});

Alpine.start();
