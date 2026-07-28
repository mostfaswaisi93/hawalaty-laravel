/**
 * Keeps already-visited pages readable when the connection drops.
 *
 * Served from /public so its scope is the whole origin. Not built by Vite: a
 * hashed filename would register as a NEW worker on every deploy while the old
 * one kept serving, and the URL a service worker is registered from has to be
 * stable.
 *
 * It caches nothing eagerly. A precache list of hashed asset names goes stale
 * the moment the app is rebuilt, and guessing which pages matter is worse than
 * letting the staff's own usage decide - what they opened is what they need.
 */

const CACHE = 'hawalaty-v1';

/**
 * Pages are NETWORK-FIRST. Tenant screens show money that changes minute to
 * minute, so a cached copy is a fallback, never a preference - serving a stale
 * balance to someone who is online would be worse than a slow load.
 */
async function pageStrategy(request) {
    try {
        const response = await fetch(request);

        if (response.ok) {
            const cache = await caches.open(CACHE);
            cache.put(request, response.clone());
        }

        return response;
    } catch {
        const cached = await caches.match(request);
        if (cached) return cached;

        return new Response(offlineHtml(), {
            status: 503,
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
        });
    }
}

/**
 * Build assets are CACHE-FIRST. Their filenames carry a content hash, so a
 * cached one can never be the wrong version - a change produces a new name.
 */
async function assetStrategy(request) {
    const cached = await caches.match(request);
    if (cached) return cached;

    const response = await fetch(request);

    if (response.ok) {
        const cache = await caches.open(CACHE);
        cache.put(request, response.clone());
    }

    return response;
}

self.addEventListener('install', (event) => {
    // Take over immediately rather than waiting for every tab to close - staff
    // keep the order screen open all day and would otherwise never get updates.
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const keys = await caches.keys();
            await Promise.all(
                keys.filter((k) => k.startsWith('hawalaty-') && k !== CACHE).map((k) => caches.delete(k)),
            );
            await self.clients.claim();
        })(),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Only ever GET, and only this origin. A POST must reach the server or
    // fail loudly so the page can queue it - answering one from a cache would
    // tell the user an order was accepted when nothing was recorded.
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    // Never cache authentication or the sync drain. A cached /login would show
    // a signed-out shell to someone who is signed in, and a stale CSRF token
    // fails every subsequent post.
    if (/^\/(login|logout|sync|api)\b/.test(url.pathname)) return;

    if (request.mode === 'navigate') {
        event.respondWith(pageStrategy(request));

        return;
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(assetStrategy(request));
    }
});

/** Shown only when a page was never visited online. Deliberately dependency-free. */
function offlineHtml() {
    return `<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>لا يوجد اتصال - حوالاتي</title>
<style>
  body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
         background:#f7f8fc; color:#1a1c22; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
  .card { max-width:26rem; padding:2.5rem 2rem; text-align:center; }
  h1 { font-size:1.35rem; margin:0 0 .5rem; }
  p  { margin:0 0 1.5rem; color:#5b5f6b; line-height:1.7; }
  button { border:0; border-radius:.75rem; background:#00236f; color:#fff;
           padding:.75rem 1.75rem; font:inherit; font-weight:700; cursor:pointer; }
  @media (prefers-color-scheme: dark) {
    body { background:#111318; color:#e4e6ee; }
    p { color:#c2c7d2; }
    button { background:#b6c4ff; color:#00236f; }
  }
</style>
</head>
<body>
  <div class="card">
    <h1>لا يوجد اتصال</h1>
    <p>هذه الصفحة لم تُفتح من قبل، فلا توجد نسخة محفوظة منها. الطلبات التي أنشأتها بدون اتصال ما زالت محفوظة وستُرسل تلقائياً عند عودة الشبكة.</p>
    <button onclick="location.reload()">إعادة المحاولة</button>
  </div>
</body>
</html>`;
}
