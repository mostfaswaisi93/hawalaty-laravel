

const DB_NAME = 'hawalaty-offline';
const DB_VERSION = 1;
const STORE = 'pending-orders';

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

let flushing = false;

export async function flush() {
    if (flushing || !navigator.onLine) return null;

    const queued = await pendingOrders();
    if (!queued.length) return null;

    flushing = true;

    try {
        const response = await fetch('/sync/push', {
            method: 'POST',

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

        if (!response.ok) {
            return { ok: false, status: response.status, kept: queued.length };
        }

        const result = await response.json();

        if (!Array.isArray(result?.applied) || !Array.isArray(result?.alreadyApplied)) {
            return { ok: false, status: response.status, kept: queued.length };
        }

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
        return { ok: false, status: 0, kept: queued.length };
    } finally {
        flushing = false;
    }
}

export async function clearAll() {
    await tx('readwrite', (store) => store.clear());

    if ('caches' in window) {
        const keys = await caches.keys();
        await Promise.all(keys.filter((k) => k.startsWith('hawalaty-')).map((k) => caches.delete(k)));
    }
}

export function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return;

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
        });
    });
}
