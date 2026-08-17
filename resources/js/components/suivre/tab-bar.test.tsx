import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';

const state = vi.hoisted(() => ({ url: '/insights' }));

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ url: state.url, props: {} }),
    Link: ({
        href,
        children,
        ...props
    }: {
        href: string | { url: string };
        children: ReactNode;
    }) => {
        const resolved = typeof href === 'string' ? href : href.url;

        return (
            <a href={resolved} {...props}>
                {children}
            </a>
        );
    },
}));

import { TabBar } from './tab-bar';

describe('TabBar', () => {
    it('marks the tab matching the current route active', () => {
        state.url = '/insights';
        render(<TabBar />);

        expect(
            screen.getByRole('link', { name: 'Insights' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(
            screen.getByRole('link', { name: 'Calendar' }),
        ).not.toHaveAttribute('aria-current');
        expect(
            screen.getByRole('link', { name: 'Settings' }),
        ).not.toHaveAttribute('aria-current');
    });

    it('keeps Calendar active on a nested month route', () => {
        state.url = '/calendar/2026-07';
        render(<TabBar />);

        expect(
            screen.getByRole('link', { name: 'Calendar' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(
            screen.getByRole('link', { name: 'Insights' }),
        ).not.toHaveAttribute('aria-current');
    });

    it('sits on the glass token rather than a hand-written blur', () => {
        state.url = '/insights';
        const { container } = render(<TabBar />);
        const nav = container.querySelector('nav');

        expect(nav?.className).toContain('glass');
        expect(nav?.className).not.toMatch(/backdrop-blur|bg-sidebar\//);
    });

    it('points the indicator at the active tab by index', () => {
        state.url = '/insights';
        const { container } = render(<TabBar />);
        const indicator = container.querySelector('[data-slot="tab-indicator"]');

        expect(indicator).not.toBeNull();
        expect(indicator?.getAttribute('style')).toContain('--tab-index');
        expect(indicator?.getAttribute('style')).toContain('--tab-count: 3');
    });

    it('fuses its two pills through the shared gooey filter', () => {
        state.url = '/insights';
        const { container } = render(<TabBar />);
        const indicator = container.querySelector('[data-slot="tab-indicator"]');

        // jsdom serialises url(#gooey) with quotes around the fragment, so
        // match on the referenced id rather than the exact CSS function text.
        expect(indicator?.getAttribute('style')).toContain('#gooey');
        expect(indicator?.querySelectorAll('span')).toHaveLength(2);
    });

    it('removes the travel under reduced motion and keeps the pill', () => {
        state.url = '/insights';
        const { container } = render(<TabBar />);
        const pills = container.querySelectorAll('[data-slot="tab-indicator"] span');

        for (const pill of pills) {
            expect(pill.className).toContain('motion-reduce:transition-none');
        }
    });

    it('hides the indicator from assistive technology, which reads aria-current instead', () => {
        state.url = '/insights';
        const { container } = render(<TabBar />);

        expect(container.querySelector('[data-slot="tab-indicator"]')?.getAttribute('aria-hidden')).toBe('true');
    });
});
