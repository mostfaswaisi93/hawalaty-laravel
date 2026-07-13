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

Alpine.data('globalSearch', () => ({
    query: '',
    open: false,
    loading: false,
    searched: false,
    results: { orders: [], customers: [], banks: [], menuItems: [] },
    debounceTimer: null,

    get hasResults() {
        return (
            this.results.orders.length > 0 ||
            this.results.customers.length > 0 ||
            this.results.banks.length > 0 ||
            this.results.menuItems.length > 0
        );
    },

    onInput() {
        clearTimeout(this.debounceTimer);

        if (this.query.trim().length < 2) {
            this.results = { orders: [], customers: [], banks: [], menuItems: [] };
            this.searched = false;
            this.open = false;
            return;
        }

        this.debounceTimer = setTimeout(() => this.search(), 300);
    },

    search() {
        const query = this.query.trim();
        this.loading = true;

        fetch(`/search?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((data) => {
                if (this.query.trim() !== query) return;
                this.results = data;
                this.searched = true;
                this.open = true;
            })
            .finally(() => {
                this.loading = false;
            });
    },
}));

Alpine.start();
