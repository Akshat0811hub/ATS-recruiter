import Alpine from 'alpinejs';

window.Alpine = Alpine;

function responseError(response) {
    if (response.status === 401 || response.status === 419 || response.redirected) {
        return 'Your session has expired. Sign in again to continue.';
    }
    if (response.status === 403) return 'You do not have access to this record.';
    return 'We could not load this information. Please try again.';
}

export function createOrbitShell() {
    let searchController;
    let previewController;
    let previousFocus;

    return {
        sidebarCollapsed: false,
        mobileMenuOpen: false,
        commandPaletteOpen: false,
        quickCreateOpen: false,
        searchQuery: '',
        searchResults: [],
        searchLoading: false,
        searchError: '',
        previewDrawerOpen: false,
        previewCandidate: null,
        previewLoading: false,
        previewError: '',
        init() {
            this.onKeydown = (event) => {
                if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                    event.preventDefault();
                    this.commandPaletteOpen ? this.closeSearch() : this.openSearch();
                }
                if (event.key === 'Escape') {
                    if (this.commandPaletteOpen) this.closeSearch();
                    if (this.previewDrawerOpen) this.closePreview();
                    this.quickCreateOpen = false;
                    this.mobileMenuOpen = false;
                }
            };
            window.addEventListener('keydown', this.onKeydown);
        },
        destroy() {
            window.removeEventListener('keydown', this.onKeydown);
            searchController?.abort();
            previewController?.abort();
        },
        openSearch() {
            previousFocus = document.activeElement;
            this.commandPaletteOpen = true;
            this.$nextTick(() => this.$refs.globalSearchInput?.focus());
        },
        closeSearch() {
            this.commandPaletteOpen = false;
            searchController?.abort();
            this.searchLoading = false;
            previousFocus?.focus();
        },
        closePreview() {
            this.previewDrawerOpen = false;
            previewController?.abort();
            this.previewLoading = false;
            previousFocus?.focus();
        },
        async performGlobalSearch() {
            searchController?.abort();
            const q = this.searchQuery.trim();
            this.searchError = '';
            this.searchResults = [];
            if (q.length < 2) {
                this.searchLoading = false;
                return;
            }
            const controller = new AbortController();
            searchController = controller;
            this.searchLoading = true;
            try {
                const response = await fetch(`/global-search?q=${encodeURIComponent(q)}`, {
                    headers: { Accept: 'application/json' }, signal: controller.signal,
                });
                if (!response.ok || response.redirected) throw new Error(responseError(response));
                const data = await response.json();
                if (searchController === controller) this.searchResults = data.results || [];
            } catch (error) {
                if (error.name !== 'AbortError' && searchController === controller) {
                    this.searchError = error instanceof TypeError ? 'Connection lost. Check your internet and try again.' : error.message;
                }
            } finally {
                if (searchController === controller) this.searchLoading = false;
            }
        },
        async openCandidatePreview(candidateId) {
            previewController?.abort();
            previousFocus = document.activeElement;
            this.previewDrawerOpen = true;
            this.previewLoading = true;
            this.previewCandidate = null;
            this.previewError = '';
            const controller = new AbortController();
            previewController = controller;
            this.$nextTick(() => document.querySelector('[aria-label="Close preview"]')?.focus());
            try {
                const response = await fetch(`/candidates/${candidateId}/preview`, {
                    headers: { Accept: 'application/json' }, signal: controller.signal,
                });
                if (!response.ok || response.redirected) throw new Error(responseError(response));
                const data = await response.json();
                if (previewController === controller) this.previewCandidate = data;
            } catch (error) {
                if (error.name !== 'AbortError' && previewController === controller) this.previewError = error instanceof TypeError ? 'Connection lost. Close this preview and try again.' : error.message;
            } finally {
                if (previewController === controller) this.previewLoading = false;
            }
        },
        formatMoney(value, currency = 'INR') {
            if (value === null || value === undefined || value === '') return 'Not provided';
            try {
                return new Intl.NumberFormat('en-IN', { style: 'currency', currency: currency || 'INR', maximumFractionDigits: 0 }).format(value);
            } catch {
                return `${value} ${currency || ''}`.trim();
            }
        },
    };
}

document.addEventListener('alpine:init', () => Alpine.data('orbitShell', createOrbitShell));
Alpine.start();

// Native form submission stays server-confirmed; prevent accidental double clicks.
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() === 'get' || event.defaultPrevented) return;
    if (form.dataset.submitting === 'true') {
        event.preventDefault();
        return;
    }
    form.dataset.submitting = 'true';
    form.setAttribute('aria-busy', 'true');
    setTimeout(() => form.querySelectorAll('button[type="submit"]').forEach((button) => {
        button.disabled = true;
    }), 0);
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting]').forEach((form) => {
        delete form.dataset.submitting;
        form.removeAttribute('aria-busy');
        form.querySelectorAll('button[type="submit"]').forEach((button) => { button.disabled = false; });
    });
});

// Associate legacy labels with their fields without changing request names.
document.querySelectorAll('label:not([for])').forEach((label, index) => {
    const field = label.nextElementSibling;
    if (field?.matches('input, select, textarea')) {
        field.id ||= `field-${index}`;
        label.htmlFor = field.id;
    }
});

// Keep keyboard focus within an open dialog.
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Tab') return;
    const dialog = [...document.querySelectorAll('[data-focus-dialog], [role="dialog"]')].find((element) => element.getClientRects().length);
    if (!dialog) return;
    const focusable = [...dialog.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select, textarea, [tabindex="0"]')].filter((element) => element.getClientRects().length);
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (!dialog.contains(document.activeElement) || (event.shiftKey && document.activeElement === first)) {
        event.preventDefault();
        (event.shiftKey ? last : first).focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
});
