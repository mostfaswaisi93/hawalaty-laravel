/**
 * Taking orders with no connection.
 *
 * The scope is deliberate and narrow: an order written while the connection is
 * down is kept locally and sent when it returns, and pages already visited stay
 * readable. Everything else - editing, deleting, config screens - still requires
 * the network, because a queued edit needs conflict resolution that a shop
 * counter does not benefit from.
 *
 * WHY IndexedDB AND NOT localStorage: a queued order must survive a tab crash, a
 * phone running out of battery mid-shift, and a browser reclaiming memory.
 * localStorage is synchronous (it blocks the order screen on every write) and
 * capped at a few megabytes shared with everything else on the origin.
 *
 * WHY THE QUEUE POSTS TO THE WEB ROUTE and not /api/v1/sync/push: the page is
 * authenticated by a session cookie. Calling the API version would mean keeping
 * a Sanctum token in the browser, which is an XSS-readable credential worth far
 * more than the queue it would serve.
 */

const DB_NAME = 'hawalaty-offline';
const DB_VERSION = 1;
const STORE = 'pending-orders';

/** Opened once and reused; every call awaits the same promise. */
let dbPromise = null;

function openDb() {
    if (dbPromise) return dbPromise;

    dbPromise = new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains(STORE)) {
                db.createObjectStore(STORE, { keyPath: 'clientUuid' });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });

    return dbPromise;
}

function tx(mode, run) {
    return openDb().then(
        (db) =>
            new Promise((resolve, reject) => {
                const transaction = db.transaction(STORE, mode);
                const result = run(transaction.objectStore(STORE));

                transaction.oncomplete = () => resolve(result?.result ?? result);
                transaction.onerror = () => reject(transaction.error);
            }),
    );
}

/**
 * A uuid the server will treat as this order's identity forever - it is the
 * idempotency key, so a resend after a half-delivered push cannot create a
 * second order. crypto.randomUUID is unavailable on insecure origins, hence
 * the fallback.
 */
function uuid() {
    if (crypto.randomUUID) return crypto.randomUUID();

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (crypto.getRandomValues(new Uint8Array(1))[0] % 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
}

export async function queueOrder(payload) {
    const record = { clientUuid: uuid(), payload, queuedAt: new Date().toISOString() };

    await tx('readwrite', (store) => store.add(record));

    return record;
}

export function pendingOrders() {
    return tx('readonly', (store) => store.getAll());
}

export async function pendingCount() {
    return (await pendingOrders()).length;
}

function forget(clientUuid) {
    return tx('readwrite', (store) => store.delete(clientUuid));
}

/** Prevents two flushes racing - `online` can fire while one is mid-flight. */
let flushing = false;

/**
 * Sends everything queued. Returns what happened so the caller can tell the
 * user something specific rather than a shrug.
 */
export async function flush() {
    if (flushing || !navigator.onLine) return null;

    const queued = await pendingOrders();
    if (!queued.length) return null;

    flushing = true;

    try {
        const response = await fetch('/sync/push', {
            method: 'POST',
            // MANUAL, not the default 'follow'. A session that timed out during
            // a long shift redirects to /login, and following that returns 200
            // HTML - so `response.ok` was true and the only thing that saved the
            // queue was JSON.parse choking on markup. Orders were kept by an
            // exception rather than by design, and a login page that ever
            // answered in JSON would have made this report a SUCCESSFUL flush
            // with nothing sent, hiding a dead session while orders piled up.
            redirect: 'manual',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                events: queued.map((record) => ({
                    clientUuid: record.clientUuid,
                    entityType: 'transfer',
                    operation: 'create',
                    payload: record.payload,
                })),
            }),
        });

        // A redirect now arrives as an opaque response: status 0, ok false.
        // 419 is an expired CSRF token, 401/403 a dead session. In every case
        // the orders are fine and this browser simply cannot post right now,
        // so they stay queued - dropping them would lose real sales.
        if (!response.ok) {
            return { ok: false, status: response.status, kept: queued.length };
        }

        const result = await response.json();

        // The envelope must actually be ours. Anything else is not a verdict on
        // these orders, and treating it as one would forget them silently.
        if (!Array.isArray(result?.applied) || !Array.isArray(result?.alreadyApplied)) {
            return { ok: false, status: response.status, kept: queued.length };
        }

        // Applied AND already-applied both mean the server has it. A failed one
        // stays queued only if it could still succeed later; a validation
        // failure never will, so it is dropped rather than retried forever.
        const done = [...(result.applied ?? []), ...(result.alreadyApplied ?? [])];
        const permanentlyFailed = (result.failed ?? []).map((f) => f.clientUuid).filter(Boolean);

        await Promise.all([...done, ...permanentlyFailed].map(forget));

        return {
            ok: true,
            sent: done.length,
            rejected: permanentlyFailed.length,
            rejectedDetail: result.failed ?? [],
        };
    } catch {
        // The network dropped again mid-push. Everything stays queued.
        return { ok: false, status: 0, kept: queued.length };
    } finally {
        flushing = false;
    }
}

/**
 * Wipes the queue and the page cache. Called on sign-out: the next person at
 * this browser must not find the previous one's orders or cached screens.
 */
export async function clearAll() {
    await tx('readwrite', (store) => store.clear());

    if ('caches' in window) {
        const keys = await caches.keys();
        await Promise.all(keys.filter((k) => k.startsWith('hawalaty-')).map((k) => caches.delete(k)));
    }
}

export function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return;

    // Insecure origins have no service worker; the queue still works there, the
    // pages just are not cached.
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            /* registration is an enhancement - never break the app over it */
        });
    });
}
