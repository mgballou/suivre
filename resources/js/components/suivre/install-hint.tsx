import { Share } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';

const DISMISSED_KEY = 'suivre:install-hint-dismissed';

/**
 * Whether this is an iOS browser that could install the app but has not.
 *
 * iOS has no `beforeinstallprompt` — Safari will never offer to install
 * anything, and there is no API to ask. Add to Home Screen is a manual gesture
 * buried in the share sheet, so a first-time user simply never finds it. Telling
 * them where it is *is* the install prompt on this platform.
 *
 * `MSStream` is checked because old IE on Windows Phone also matched
 * `/iPad|iPhone|iPod/`; it costs one clause and avoids a wrong hint.
 */
function canInstallOnIos(): boolean {
    if (typeof navigator === 'undefined' || typeof window === 'undefined') {
        return false;
    }

    const isIos =
        /iPad|iPhone|iPod/.test(navigator.userAgent) &&
        !('MSStream' in window);

    // iPadOS reports itself as a Mac; the touch points give it away.
    const isIpad =
        navigator.userAgent.includes('Macintosh') && navigator.maxTouchPoints > 1;

    const isInstalled =
        window.matchMedia('(display-mode: standalone)').matches ||
        ('standalone' in navigator && navigator.standalone === true);

    return (isIos || isIpad) && !isInstalled;
}

/**
 * A one-time note on how to put Suivre on the home screen.
 *
 * Shown once and dismissible for good. The app is a daily habit and the
 * difference between a Safari tab and a home-screen icon is most of whether
 * that habit forms — but a banner that reappears is an app nagging its user,
 * which the quiet-instrument voice rules out (D20).
 */
export function InstallHint() {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        if (localStorage.getItem(DISMISSED_KEY) === null && canInstallOnIos()) {
            setVisible(true);
        }
    }, []);

    if (!visible) {
        return null;
    }

    const dismiss = () => {
        localStorage.setItem(DISMISSED_KEY, '1');
        setVisible(false);
    };

    return (
        <div
            role="note"
            className="mx-4 mb-2 flex items-start gap-3 rounded-md border border-border bg-card px-3 py-3 text-sm sm:mx-6"
        >
            <Share className="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden />

            <div className="flex flex-1 flex-col gap-2">
                <p className="text-muted-foreground">
                    Keep Suivre on your home screen: tap Share, then{' '}
                    <span className="text-foreground">Add to Home Screen</span>.
                </p>

                <div>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={dismiss}
                        className="-ml-2"
                    >
                        Got it
                    </Button>
                </div>
            </div>
        </div>
    );
}
