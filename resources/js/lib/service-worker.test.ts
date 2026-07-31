import { afterEach, describe, expect, it, vi } from 'vitest';
import { registerServiceWorker } from './service-worker';

function stubServiceWorkerContainer(register = vi.fn().mockResolvedValue(undefined)) {
    Object.defineProperty(navigator, 'serviceWorker', {
        value: { register },
        configurable: true,
    });

    return register;
}

/** Fires the `load` listener `registerServiceWorker` defers registration to. */
function load(): void {
    window.dispatchEvent(new Event('load'));
}

describe('registerServiceWorker', () => {
    afterEach(() => {
        vi.unstubAllEnvs();
        Reflect.deleteProperty(navigator, 'serviceWorker');
    });

    it('registers at the root scope so the worker controls the whole app', () => {
        vi.stubEnv('PROD', true);
        const register = stubServiceWorkerContainer();

        registerServiceWorker();
        load();

        expect(register).toHaveBeenCalledWith('/sw.js', { scope: '/' });
    });

    it('does not register in development, where Vite serves unhashed modules', () => {
        vi.stubEnv('PROD', false);
        const register = stubServiceWorkerContainer();

        registerServiceWorker();
        load();

        expect(register).not.toHaveBeenCalled();
    });

    it('does nothing where the browser has no service worker support', () => {
        vi.stubEnv('PROD', true);

        expect(() => {
            registerServiceWorker();
            load();
        }).not.toThrow();
    });
});
