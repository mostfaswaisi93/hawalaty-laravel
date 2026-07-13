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

Alpine.start();
