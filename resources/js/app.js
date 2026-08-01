import Alpine from 'alpinejs';
import { clearAll, flush, pendingCount, queueOrder, registerServiceWorker } from './offline.js';

window.Alpine = Alpine;

registerServiceWorker();

Alpine.store('offline', {
    online: navigator.onLine,
    pending: 0,

    lastResult: null,

    async init() {
        this.pending = await pendingCount();

        window.addEventListener('online', () => {
            this.online = true;
            this.drain();
        });

        window.addEventListener('offline', () => {
            this.online = false;
        });

        if (this.online) this.drain();
    },

    async queue(payload) {
        await queueOrder(payload);
        this.pending = await pendingCount();
    },

    async drain() {
        const result = await flush();
        this.pending = await pendingCount();

        if (result) this.lastResult = result;

        return result;
    },

    async wipe() {
        await clearAll();
        this.pending = 0;
    },
});

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
const EMPTY_RESULTS = () => ({ orders: [], customers: [], banks: [], menuItems: [], teamMembers: [] });

Alpine.data('globalSearch', () => ({
    query: '',
    open: false,
    mobileOpen: false,
    loading: false,
    searched: false,
    results: EMPTY_RESULTS(),
    itemLabel: '',

    cursor: -1,
    recent: [],
    debounceTimer: null,

    controller: null,

    init() {
        this.recent = this.readRecent();

        window.addEventListener('keydown', (e) => {
            if (!((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k')) return;
            e.preventDefault();
            this.focusSearch();
        });
    },

    focusSearch() {
        if (window.matchMedia('(min-width: 640px)').matches) {
            this.$refs.desktopSearchInput?.focus();
            this.$refs.desktopSearchInput?.select();
            if (this.hasResults) this.open = true;
            return;
        }

        this.mobileOpen = true;
        this.$nextTick(() => this.$refs.mobileSearchInput?.focus());
    },

    get flat() {
        return [
            ...this.results.orders,
            ...this.results.customers,
            ...this.results.banks,
            ...this.results.menuItems,
            ...this.results.teamMembers,
        ];
    },

    get hasResults() {
        return this.flat.length > 0;
    },

    get showPanel() {
        return this.hasResults || (this.searched && !this.loading) || this.showRecent;
    },

    get showRecent() {
        return !this.searched && this.query.trim().length < SEARCH_MIN && this.recent.length > 0;
    },

    indexOf(group, i) {
        const before = {
            orders: 0,
            customers: this.results.orders.length,
            banks: this.results.orders.length + this.results.customers.length,
            menuItems:
                this.results.orders.length +
                this.results.customers.length +
                this.results.banks.length,
            teamMembers:
                this.results.orders.length +
                this.results.customers.length +
                this.results.banks.length +
                this.results.menuItems.length,
        };

        return before[group] + i;
    },

    move(step) {
        if (!this.hasResults) return;

        const total = this.flat.length;

        this.cursor = (this.cursor + step + total) % total;
        this.open = true;

        this.$nextTick(() => {
            [...this.$root.querySelectorAll(`[data-result-index="${this.cursor}"]`)]
                .find((el) => el.offsetParent !== null)
                ?.scrollIntoView({ block: 'nearest' });
        });
    },

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
                    teamMembers: data.teamMembers ?? [],
                };
                this.itemLabel = data.itemLabel ?? '';
                this.searched = true;
                this.cursor = -1;
                this.open = true;
                this.loading = false;
            })
            .catch((e) => {
                if (e.name === 'AbortError') return;
                this.searched = true;
                this.results = EMPTY_RESULTS();
                this.loading = false;
            });
    },

    readRecent() {
        try {
            const raw = JSON.parse(localStorage.getItem(SEARCH_RECENT_KEY) ?? '[]');
            return Array.isArray(raw) ? raw.filter((s) => typeof s === 'string').slice(0, SEARCH_RECENT_MAX) : [];
        } catch {
            return [];
        }
    },

    remember(term) {
        if (term.length < SEARCH_MIN) return;

        this.recent = [term, ...this.recent.filter((t) => t !== term)].slice(0, SEARCH_RECENT_MAX);

        try {
            localStorage.setItem(SEARCH_RECENT_KEY, JSON.stringify(this.recent));
        } catch {
        }
    },

    clearRecent() {
        this.recent = [];
        try {
            localStorage.removeItem(SEARCH_RECENT_KEY);
        } catch {
        }
    },

    runRecent(term) {
        this.query = term;
        this.search();
    },

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
