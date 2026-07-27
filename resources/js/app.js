import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('notificationBell', (initialUnread = 0) => ({
    open: false,
    loading: false,
    loaded: false,
    notifications: [],
    unreadCount: initialUnread,

    toggle() {
        this.open = !this.open;
        if (this.open && !this.loaded) {
            this.fetchNotifications();
        }
    },

    fetchNotifications() {
        this.loading = true;
        fetch('/notifications', { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((data) => {
                this.notifications = data.notifications;
                this.unreadCount = data.unreadCount;
                this.loaded = true;
            })
            .finally(() => {
                this.loading = false;
            });
    },

    csrfToken() {
        return document.querySelector('meta[name="csrf-token"]').content;
    },

    markRead(n) {
        if (n.readAt) return;

        fetch(`/notifications/${n.id}/read`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': this.csrfToken(), Accept: 'application/json' },
        })
            .then((r) => r.json())
            .then((data) => {
                n.readAt = new Date().toISOString();
                this.unreadCount = data.unreadCount;
            });
    },

    markAllRead() {
        fetch('/notifications/read-all', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': this.csrfToken(), Accept: 'application/json' },
        })
            .then((r) => r.json())
            .then(() => {
                this.notifications = this.notifications.map((n) => ({
                    ...n,
                    readAt: n.readAt || new Date().toISOString(),
                }));
                this.unreadCount = 0;
            });
    },
}));

const SEARCH_MIN = 2;
const SEARCH_RECENT_KEY = 'hawalaty:recent-searches';
const SEARCH_RECENT_MAX = 5;
const EMPTY_RESULTS = () => ({ orders: [], customers: [], banks: [], menuItems: [] });

Alpine.data('globalSearch', () => ({
    query: '',
    open: false,
    mobileOpen: false,
    loading: false,
    searched: false,
    results: EMPTY_RESULTS(),
    itemLabel: '',
    /** Index into `flat`; -1 means "nothing highlighted, Enter does nothing". */
    cursor: -1,
    recent: [],
    debounceTimer: null,
    /** In-flight request, so a slow answer can never overwrite a newer one. */
    controller: null,

    init() {
        this.recent = this.readRecent();

        // Ctrl/Cmd+K focuses the search from anywhere, the convention every
        // app with a header search follows. `/` would collide with typing.
        window.addEventListener('keydown', (e) => {
            if (!((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k')) return;
            e.preventDefault();
            this.focusSearch();
        });
    },

    focusSearch() {
        // Under `sm` the desktop pill is display:none and cannot take focus,
        // so the shortcut has to open the overlay instead.
        if (window.matchMedia('(min-width: 640px)').matches) {
            this.$refs.desktopSearchInput?.focus();
            this.$refs.desktopSearchInput?.select();
            if (this.hasResults) this.open = true;
            return;
        }

        this.mobileOpen = true;
        this.$nextTick(() => this.$refs.mobileSearchInput?.focus());
    },

    /**
     * Every visible result in render order. Keyboard navigation needs one flat
     * list; the four groups are a display detail.
     */
    get flat() {
        return [
            ...this.results.orders,
            ...this.results.customers,
            ...this.results.banks,
            ...this.results.menuItems,
        ];
    },

    get hasResults() {
        return this.flat.length > 0;
    },

    /** True when the panel should show something - results, "none", or hints. */
    get showPanel() {
        return this.hasResults || (this.searched && !this.loading) || this.showRecent;
    },

    get showRecent() {
        return !this.searched && this.query.trim().length < SEARCH_MIN && this.recent.length > 0;
    },

    /** Global index of a row, so each group can offset its own local index. */
    indexOf(group, i) {
        const before = {
            orders: 0,
            customers: this.results.orders.length,
            banks: this.results.orders.length + this.results.customers.length,
            menuItems:
                this.results.orders.length +
                this.results.customers.length +
                this.results.banks.length,
        };

        return before[group] + i;
    },

    move(step) {
        if (!this.hasResults) return;

        const total = this.flat.length;
        // Wraps, so ArrowUp from the top reaches the last result.
        this.cursor = (this.cursor + step + total) % total;
        this.open = true;

        this.$nextTick(() => {
            // The desktop panel and the mobile overlay both render the row, so
            // this has to scroll the one actually on screen - `offsetParent` is
            // null for the hidden copy.
            [...this.$root.querySelectorAll(`[data-result-index="${this.cursor}"]`)]
                .find((el) => el.offsetParent !== null)
                ?.scrollIntoView({ block: 'nearest' });
        });
    },

    /** Enter on a highlighted row navigates to it. */
    choose() {
        const hit = this.flat[this.cursor];
        if (!hit?.url) return;

        this.remember(this.query.trim());
        window.location.href = hit.url;
    },

    closeMobile() {
        this.mobileOpen = false;
        this.reset();
    },

    reset() {
        this.query = '';
        this.results = EMPTY_RESULTS();
        this.searched = false;
        this.cursor = -1;
        this.open = false;
    },

    onInput() {
        clearTimeout(this.debounceTimer);
        this.cursor = -1;

        if (this.query.trim().length < SEARCH_MIN) {
            // Abort whatever is still in flight: its answer is for a query the
            // user has already deleted past.
            this.controller?.abort();
            this.results = EMPTY_RESULTS();
            this.searched = false;
            this.loading = false;
            this.open = this.showRecent;
            return;
        }

        this.debounceTimer = setTimeout(() => this.search(), 250);
    },

    search() {
        const query = this.query.trim();

        // One request at a time. The old code let stale responses race and
        // relied on comparing the query back; aborting is the actual fix.
        this.controller?.abort();
        this.controller = new AbortController();

        this.loading = true;

        fetch(`/search?q=${encodeURIComponent(query)}`, {
            headers: { Accept: 'application/json' },
            signal: this.controller.signal,
        })
            .then((r) => (r.ok ? r.json() : Promise.reject(new Error(r.status))))
            .then((data) => {
                this.results = {
                    orders: data.orders ?? [],
                    customers: data.customers ?? [],
                    banks: data.banks ?? [],
                    menuItems: data.menuItems ?? [],
                };
                this.itemLabel = data.itemLabel ?? '';
                this.searched = true;
                this.cursor = -1;
                this.open = true;
                this.loading = false;
            })
            .catch((e) => {
                // An abort is the expected path, not a failure: a newer request
                // has already taken over and will set `loading` itself.
                if (e.name === 'AbortError') return;
                this.searched = true;
                this.results = EMPTY_RESULTS();
                this.loading = false;
            });
    },

    // --- recent searches -------------------------------------------------

    readRecent() {
        try {
            const raw = JSON.parse(localStorage.getItem(SEARCH_RECENT_KEY) ?? '[]');
            return Array.isArray(raw) ? raw.filter((s) => typeof s === 'string').slice(0, SEARCH_RECENT_MAX) : [];
        } catch {
            // Private mode, or someone put junk in the key. Not worth a crash.
            return [];
        }
    },

    remember(term) {
        if (term.length < SEARCH_MIN) return;

        this.recent = [term, ...this.recent.filter((t) => t !== term)].slice(0, SEARCH_RECENT_MAX);

        try {
            localStorage.setItem(SEARCH_RECENT_KEY, JSON.stringify(this.recent));
        } catch {
            /* storage full or blocked - the feature is a convenience, not a requirement */
        }
    },

    clearRecent() {
        this.recent = [];
        try {
            localStorage.removeItem(SEARCH_RECENT_KEY);
        } catch {
            /* see remember() */
        }
    },

    runRecent(term) {
        this.query = term;
        this.search();
    },

    /**
     * Splits a value around the matched term so the template can bold the part
     * that matched. Returns plain strings for x-text, never markup - building
     * HTML here would put user data straight into the DOM.
     */
    parts(value) {
        const text = String(value ?? '');
        const term = this.query.trim();
        if (!term) return [{ text, hit: false }];

        const at = text.toLowerCase().indexOf(term.toLowerCase());
        if (at === -1) return [{ text, hit: false }];

        return [
            { text: text.slice(0, at), hit: false },
            { text: text.slice(at, at + term.length), hit: true },
            { text: text.slice(at + term.length), hit: false },
        ].filter((p) => p.text !== '');
    },
}));

Alpine.start();
