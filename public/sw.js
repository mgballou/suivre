/*
 * Suivre service worker — app-shell asset caching, online-first.
 *
 * Scope is deliberately narrow: only Vite's hashed build output under
 * `/build/` is ever cached. Two constraints force that shape.
 *
 * Documents are never cached. The first Inertia response embeds the page's
 * props — a user's conditions, ratings and flares — directly in the HTML, so a
 * cached document is cached health data sitting on disk and, on a shared
 * device, servable to the next person to open the app. Data responses are
 * never cached for the same reason plus the MVP rule: stale health data is
 * worse than none.
 *
 * Hashed assets, by contrast, are immutable — the URL changes when the content
 * does — so cache-first can never serve a stale chunk. That is what makes this
 * safe alongside per-page code-splitting, where a precached list of chunk URLs
 * would rot at the next deploy.
 *
 * Deploy invalidation is therefore a pruning problem, not a staleness one: a
 * new build simply requests new URLs. On activate we read the current Vite
 * manifest and drop every cache entry it no longer references, so the cache
 * cannot grow without bound across deploys.
 */

const CACHE_NAME = 'suivre-assets-v1';
const ASSET_PREFIX = '/build/';
const MANIFEST_URL = '/build/manifest.json';

self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            await pruneStaleCaches();
            await self.clients.claim();
        })(),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET' || request.mode === 'navigate') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin || !url.pathname.startsWith(ASSET_PREFIX)) {
        return;
    }

    event.respondWith(cacheFirst(request));
});

async function cacheFirst(request) {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    // Opaque and error responses are passed through uncached — caching them
    // would pin a failure until the next deploy.
    if (response.ok && response.type === 'basic') {
        await cache.put(request, response.clone());
    }

    return response;
}

/**
 * Drops cache entries the current build no longer references, and any cache
 * left behind by an older worker version.
 */
async function pruneStaleCaches() {
    const names = await caches.keys();

    await Promise.all(names.filter((name) => name !== CACHE_NAME).map((name) => caches.delete(name)));

    const live = await liveAssetPaths();

    if (live === null) {
        return;
    }

    const cache = await caches.open(CACHE_NAME);
    const entries = await cache.keys();

    await Promise.all(
        entries.map((entry) => {
            const path = new URL(entry.url).pathname;

            return live.has(path) ? Promise.resolve(false) : cache.delete(entry);
        }),
    );
}

/**
 * Every asset path in the current Vite manifest, including the CSS and chunks
 * each entry pulls in. Returns null when the manifest cannot be read, so a
 * transient failure leaves the cache untouched rather than emptying it.
 */
async function liveAssetPaths() {
    try {
        const response = await fetch(MANIFEST_URL, { cache: 'no-store' });

        if (!response.ok) {
            return null;
        }

        const manifest = await response.json();
        const paths = new Set();

        for (const chunk of Object.values(manifest)) {
            for (const file of [chunk.file, ...(chunk.css ?? []), ...(chunk.assets ?? [])]) {
                if (file) {
                    paths.add(ASSET_PREFIX + file);
                }
            }
        }

        return paths;
    } catch {
        return null;
    }
}
