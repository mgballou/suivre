import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { InstallHint } from './install-hint';

const IPHONE =
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15';
const DESKTOP =
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36';

function pretend({
    userAgent = IPHONE,
    standalone = false,
    maxTouchPoints = 5,
}: {
    userAgent?: string;
    standalone?: boolean;
    maxTouchPoints?: number;
} = {}) {
    // defineProperty rather than spyOn: jsdom's navigator declares neither
    // property, and spyOn refuses to stub what does not already exist.
    Object.defineProperty(navigator, 'userAgent', {
        value: userAgent,
        configurable: true,
    });
    Object.defineProperty(navigator, 'maxTouchPoints', {
        value: maxTouchPoints,
        configurable: true,
    });

    vi.stubGlobal('matchMedia', (query: string) => ({
        matches: standalone && query.includes('standalone'),
        media: query,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
    }));
}

beforeEach(() => {
    localStorage.clear();
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('InstallHint', () => {
    it('tells an iPhone user where Add to Home Screen lives', () => {
        pretend();

        render(<InstallHint />);

        expect(screen.getByText(/Add to Home Screen/)).toBeInTheDocument();
    });

    it('stays out of the way once the app is already installed', () => {
        pretend({ standalone: true });

        const { container } = render(<InstallHint />);

        expect(container).toBeEmptyDOMElement();
    });

    it('says nothing on a platform that offers its own install path', () => {
        pretend({ userAgent: DESKTOP, maxTouchPoints: 0 });

        const { container } = render(<InstallHint />);

        expect(container).toBeEmptyDOMElement();
    });

    it('recognises an iPad, which reports itself as a Mac', () => {
        pretend({ userAgent: DESKTOP, maxTouchPoints: 5 });

        render(<InstallHint />);

        expect(screen.getByText(/Add to Home Screen/)).toBeInTheDocument();
    });

    it('never comes back once dismissed', () => {
        pretend();

        const { unmount } = render(<InstallHint />);

        fireEvent.click(screen.getByRole('button', { name: /got it/i }));

        expect(screen.queryByText(/Add to Home Screen/)).not.toBeInTheDocument();

        unmount();

        const { container } = render(<InstallHint />);

        expect(container).toBeEmptyDOMElement();
    });
});
