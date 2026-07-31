/**
 * Registers the app-shell service worker.
 *
 * Development is deliberately excluded: Vite serves unhashed modules over its
 * dev server, and the worker's cache-first rule assumes hashed, immutable
 * URLs. Registering in dev would serve yesterday's module after an edit.
 */
export function registerServiceWorker(): void {
    if (!import.meta.env.PROD || !('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener(
        'load',
        () => {
            void navigator.serviceWorker.register('/sw.js', { scope: '/' });
        },
        { once: true },
    );
}
